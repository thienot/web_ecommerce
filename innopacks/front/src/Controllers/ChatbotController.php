<?php

namespace InnoShop\Front\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InnoShop\Common\Models\ChatMessage;
use \InnoShop\Common\Models\Product;
class ChatbotController extends Controller
{
    public function ask(Request $request)
    {
        $userMsg     = trim($request->input('message'));
        $guestToken  = $request->input('guest_token');
        $customerId  = auth()->guard('customer')->id();
        $locale      = app()->getLocale();   // vi, en, zh-cn

        if (empty($userMsg)) {
            return response()->json([
                'status'  => 'error', 
                'message' => __('front/chat.empty_message', [], $locale)            
            ]);
        }

        try {
            // 1. Lưu tin nhắn của khách
            ChatMessage::create([
                'customer_id' => $customerId,
                'guest_token' => $guestToken,
                'sender'      => 'customer',
                'message'     => $userMsg
            ]);

            // 2. Tìm sản phẩm liên quan theo ngôn ngữ hiện tại
            $products = Product::where('active', true)
                ->with([
                    'translations' => fn($q) => $q->where('locale', $locale),
                    'skus'         => fn($q) => $q->where('quantity', '>', 0),
                    'brand',
                    'masterSku',
                    'productAttributes.attribute.group.translation',     // Quan trọng
                    'productAttributes.attribute.translation',           // Quan trọng
                    'productAttributes.attributeValue.translation'
                ])
                ->orderByDesc('sales')
                ->orderBy('position')
                ->limit(10)
                ->get();

            // 3. Tạo Product Context theo ngôn ngữ
            $productContext = $this->buildProductContext($products, $locale, $userMsg);

            // 4. Lấy lịch sử chat
            $history = ChatMessage::where(function ($q) use ($customerId, $guestToken) {
                    $q->when($customerId, fn($sub) => $sub->where('customer_id', $customerId))
                    ->when(!$customerId, fn($sub) => $sub->where('guest_token', $guestToken));
                })
                ->latest()
                ->take(6)
                ->get()
                ->reverse();

            // 5. Tạo System Prompt theo ngôn ngữ
            $systemPrompt = $this->buildSystemPrompt($productContext, $locale);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];

            foreach ($history as $chat) {
                $messages[] = [
                    'role'    => $chat->sender === 'customer' ? 'user' : 'assistant',
                    'content' => $chat->message
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $userMsg];

            // 6. Gọi Groq API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post("https://api.groq.com/openai/v1/chat/completions", [
                'model'       => 'llama-3.1-8b-instant',
                'messages'    => $messages,
                'temperature' => 0.75,
                'max_tokens'  => 700,
            ]);

            $botResponse = $response->json()['choices'][0]['message']['content'] 
                        ?? __('front/chat.sorry_busy', [], $locale);

            // 7. Lưu tin nhắn Bot
            ChatMessage::create([
                'customer_id' => $customerId,
                'guest_token' => $guestToken,
                'sender'      => 'bot',
                'message'     => $botResponse
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => $botResponse
            ]);

        } catch (\Exception $e) {
            \Log::error('Chatbot Error: ' . $e->getMessage());

            $errorMsg = match($locale) {
                'en'    => "Sorry, the system is busy. Please try again later.",
                'zh-cn' => "抱歉，系统繁忙，请稍后再试。",
                'vi' => "Xin lỗi, hệ thống đang bận. Bạn thử lại sau nhé!"
            };

            return response()->json([
                'status'  => 'error',
                'message' => $errorMsg
            ]);
        }
    }
        /**
     * Xây dựng context sản phẩm theo ngôn ngữ
     */
    private function buildProductContext($products, string $locale, string $userMsg): string
{
    if ($products->isEmpty()) {
        return match($locale) {
            'en'    => "No products found for: '{$userMsg}'.\n",
            'zh-cn' => "未找到匹配“{$userMsg}”的产品。\n",
            default => "Hiện tại không tìm thấy sản phẩm nào khớp với: '{$userMsg}'.\n"
        };
    }

    $header = match($locale) {
        'en'    => "Here are the current products in our store:\n",
        'zh-cn' => "以下是本店当前的产品信息：\n",
        default => "Dưới đây là thông tin sản phẩm hiện có của cửa hàng:\n"
    };

    $context = $header;

    foreach ($products as $p) {
        $trans = $p->translation ?? $p->translations->first();
        $name  = $trans?->name ?? $p->slug;

        // Giá
        $sku = $p->masterSku ?? $p->skus->first();
        $priceDisplay = $sku ? $sku->getFinalPriceFormat() : ($locale == 'vi' ? "Liên hệ" : "Contact");

        // Thông tin cơ bản
        $line = match($locale) {
            'en'    => "- {$name} | Price: {$priceDisplay} | Stock: {$p->totalQuantity()} | Link: {$p->url}\n",
            'zh-cn' => "- {$name} | 价格：{$priceDisplay} | 库存：{$p->totalQuantity()} | 链接：{$p->url}\n",
            default => "- {$name} | Giá: {$priceDisplay} | Tồn kho: {$p->totalQuantity()} | Link: {$p->url}\n"
        };

        $context .= $line;

        // ==================== THÊM THÔNG TIN KÍCH THƯỚC, SIZE, THUỘC TÍNH ====================
        if ($p->productAttributes->isNotEmpty()) {
            $context .= "  Thuộc tính:\n";
            
            $grouped = $p->groupedAttributes();   // Sử dụng hàm có sẵn của bạn

            foreach ($grouped as $group) {
                $groupName = $group['attribute_group_name'] ?? 'Thông số';
                $context .= "  - {$groupName}: ";
                
                $attrs = [];
                foreach ($group['attributes'] ?? [] as $attr) {
                    $attrs[] = "{$attr['attribute']}: {$attr['attribute_value']}";
                }
                
                $context .= implode(', ', $attrs) . "\n";
            }
        }

        $context .= "\n";
    }

    return $context;
}

    /**
     * Xây dựng System Prompt theo ngôn ngữ
     */
    private function buildSystemPrompt(string $productContext, string $locale): string
{
    return match($locale) {
        'en' => "You are a friendly and professional sales assistant at InnoShop.\n\n"
            . $productContext . "\n\n"
            . "Reply rules:\n"
            . "- Answer in a short, concise and natural way (maximum 2-3 sentences).\n"
            . "- Do not list too many products unless the customer asks.\n"
            . "- Prioritize the most relevant products.\n"
            . "- Always be helpful and encouraging.\n"
            . "- Use Vietnamese if customer speaks Vietnamese.",

        'zh-cn' => "您是InnoShop的专业友好销售助理。\n\n"
            . $productContext . "\n\n"
            . "回复规则：\n"
            . "- 回复要简洁、自然，最多2-3句话。\n"
            . "- 不要一次性列出太多产品，除非客户要求。\n"
            . "- 优先推荐最相关产品。\n"
            . "- 请用中文回复，保持友好。",

        default => "Bạn là trợ lý bán hàng vui vẻ, chuyên nghiệp của InnoShop.\n\n"
            . $productContext . "\n\n"
            . "Quy tắc trả lời:\n"
            . "- Trả lời ngắn gọn, tự nhiên, tối đa 2-3 câu.\n"
            . "- Không liệt kê quá nhiều sản phẩm trừ khi khách yêu cầu.\n"
            . "- Ưu tiên gợi ý sản phẩm phù hợp nhất.\n"
            . "- Luôn nhiệt tình, thân thiện và dùng tiếng Việt."
    };
}
    public function getHistory(Request $request)
    {
        $guestToken = $request->input('guest_token');
        $customerId = auth()->guard('customer')->id();

        $messages = ChatMessage::where(function($q) use ($customerId, $guestToken) {
            if ($customerId) $q->where('customer_id', $customerId);
            else $q->where('guest_token', $guestToken);
        })->oldest()->get();

        return response()->json($messages);
    }
}