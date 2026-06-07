<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace InnoShop\Front\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use InnoShop\Common\Models\Order;
use InnoShop\Common\Repositories\OrderRepo;
use InnoShop\Front\Services\PaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * @param  Request  $request
     * @return mixed
     * @throws Exception
     */
    public function pay(Request $request): mixed
    {
        try {
            $order = Order::query()->where('number', $request->number)->firstOrFail();

        // Generate QR code
        $qrCodeImage = null;
        if ($order) {
            $qrCodeImage = $this->generateVietQR($order);
        }
        
        // Lấy payment view từ PaymentService (nếu cần)
        $paymentService = PaymentService::getInstance($order);
        
        $data = [
            'order' => $order,
            'qr_code_image' => $qrCodeImage,  // ← Thêm QR code vào data
        ];

        // return inno_view('orders.pay', $data); // Trả về view thay vì xử lý payment

        return PaymentService::getInstance($order, $qrCodeImage)->pay();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Order detail
     *
     * @param  int  $number
     * @return mixed
     */
    public function numberShow(int $number): mixed
    {
        $order = OrderRepo::getInstance()->getOrderByNumber($number);
        $qrCodeImage = null;
        $order->load(['items', 'fees']);

        if ($order) {
            $qrCodeImage = $this->generateVietQR($order);
        }
        $data = [
            'order' => $order,
            'qr_code_image' => $qrCodeImage,
        ];

        return inno_view('orders.show', $data);
    }

    /**
     * Tạo QR code thanh toán qua VietQR API
     */
    private function generateVietQR($order)
    {
        try {
            // Cấu hình API
            $clientId = config('services.vietqr.client_id'); // Thêm vào config/services.php
            $apiKey = config('services.vietqr.api_key');
            $paymentData = currency_payment_data($order->total);

            // Chuẩn bị dữ liệu
            $data = [
                'accountNo' => config('services.vietqr.account_no', '1900086683883'),
                'accountName' => config('services.vietqr.account_name', 'Bui Duc Thien'),
                'acqId' => config('services.vietqr.bank_code', '970422'), // Mã ngân hàng
                'addInfo' => 'Thanh toan don hang #' . $order->number,
                'amount' => (string) intval(($paymentData['amount'] ?? 0)), // Chuyển về số nguyên
                'template' => 'compact'
            ];

            // Gọi API VietQR
            $response = Http::withHeaders([
                'x-client-id' => $clientId,
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.vietqr.io/v2/generate', $data);

            if ($response->successful()) {
                $result = $response->json();

                if ($result['code'] === '00' && isset($result['data']['qrDataURL'])) {
                    // Lấy base64 data từ response
                    $qrDataURL = $result['data']['qrDataURL'];

                    // Kiểm tra và xử lý base64 string
                    if (strpos($qrDataURL, 'data:image') === 0) {
                        // Nếu đã có header data:image, sử dụng trực tiếp
                        return $qrDataURL;
                    } else {
                        // Nếu chỉ là base64 thuần, thêm header
                        return 'data:image/png;base64,' . $qrDataURL;
                    }
                }
            }

            Log::error('VietQR API Error', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

        } catch (\Exception $e) {
            Log::error('VietQR Exception', [
                'message' => $e->getMessage(),
                'order_id' => $order->id
            ]);
        }

        return null;
    }

    /**
     * Lưu QR code thành file ảnh (tùy chọn)
     */
    private function saveQRCodeAsImage($base64Data, $filename)
    {
        try {
            // Loại bỏ header data:image nếu có
            if (strpos($base64Data, 'data:image') === 0) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            }

            // Decode base64
            $imageData = base64_decode($base64Data);

            // Lưu file
            $path = 'qr-codes/' . $filename . '.png';
            Storage::disk('public')->put($path, $imageData);

            return Storage::disk('public')->url($path);

        } catch (\Exception $e) {
            Log::error('Save QR Code Error', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * API endpoint để tạo QR code động
     */
    public function generateQRCode(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'amount' => 'required|numeric|min:1000'
        ]);

        try {
            $clientId = config('services.vietqr.client_id');
            $apiKey = config('services.vietqr.api_key');

            $data = [
                'accountNo' => config('services.vietqr.account_no'),
                'accountName' => config('services.vietqr.account_name'),
                'acqId' => config('services.vietqr.bank_code'),
                'addInfo' => 'Thanh toan don hang #' . $request->order_number,
                'amount' => (string) intval($request->amount),
                'template' => $request->template ?? 'compact'
            ];

            $response = Http::withHeaders([
                'x-client-id' => $clientId,
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.vietqr.io/v2/generate', $data);

            if ($response->successful()) {
                $result = $response->json();

                if ($result['code'] === '00') {
                    return response()->json([
                        'success' => true,
                        'qr_code' => $result['data']['qrCode'],
                        'qr_image' => 'data:image/png;base64,' . $result['data']['qrDataURL']
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo QR code'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
}
