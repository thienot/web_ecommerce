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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InnoShop\Common\Exceptions\Unauthorized;
use InnoShop\Common\Repositories\OrderRepo;
use InnoShop\Common\Services\CheckoutService;
use InnoShop\Common\Services\StateMachineService;
use InnoShop\Front\Requests\CheckoutConfirmRequest;
use Throwable;

class CheckoutController extends Controller
{
    /**
     * Get checkout data and render page.
     *
     * @return mixed
     * @throws Throwable
     */
    public function index(): mixed
    {
        try {
            $checkout = CheckoutService::getInstance();
            $result   = $checkout->getCheckoutResult();
            if (empty($result['cart_list'])) {
                return redirect(front_route('carts.index'))->withErrors(['error' => 'Empty Cart']);
            }

            return inno_view('checkout.index', $result);
        } catch (Unauthorized $e) {
            return redirect(front_route('login.index'))->withErrors(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            return redirect(front_route('carts.index'))->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update checkout, include shipping address, shipping method, billing address, billing method
     *
     * @param  Request  $request
     * @return mixed
     * @throws Throwable
     */
    public function update(Request $request): mixed
    {
        $data     = $request->all();
        $checkout = CheckoutService::getInstance();
        $checkout->updateValues($data);
        $result = $checkout->getCheckoutResult();

        return json_success('更新成功', $result);
    }

    /**
     * Confirm checkout and place order
     *
     * @param  CheckoutConfirmRequest  $request
     * @return mixed
     * @throws Throwable
     */
    public function confirm(CheckoutConfirmRequest $request): mixed
    {
        try {
            $checkout = CheckoutService::getInstance();
            $data     = $request->all();
            unset($data['reference']);
            if ($data) {
                $checkout->updateValues($data);
            }

            $order = $checkout->confirm();
            StateMachineService::getInstance($order)->changeStatus(StateMachineService::UNPAID, '', true);

            return json_success(front_trans('common.submitted_success'), $order);
        } catch (Exception $e) {
            return json_fail($e->getMessage());
        }
    }

    /**
     * Checkout success.
     *
     * @param  Request  $request
     * @return mixed
     * @throws Exception
     */
    public function success(Request $request): mixed
    {
        $orderNumber = $request->get('order_number');
        
        // Xử lý callback từ VNPay
        $vnpayParams = $this->getVnpayParams($request);
        $qrCodeImage = null;
        
        if (empty($orderNumber)) {
            $orderNumber = session('order_number');
        }

        if ($orderNumber) {
            $order = OrderRepo::getInstance()->builder(['number' => $orderNumber])->firstOrFail();
        }

        if (empty($order)) {
            return redirect(front_route('home.index'));
        }

        // Nếu có tham số VNPay, xử lý kết quả thanh toán
        if (!empty($vnpayParams)) {
            $this->handleVnpayCallback($order, $vnpayParams, $request);
        }

        // Tạo QR code thanh toán nếu order tồn tại
        if ($order) {
            $qrCodeImage = $this->generateVietQR($order);
        }

        $data['order'] = $order;
        $data['vnpay_result'] = $vnpayParams; // Truyền thông tin VNPay để hiển thị
        $data['qr_code_image'] = $qrCodeImage;

        return inno_view('checkout.success', $data);
    }

    /**
     * Lấy các tham số VNPay từ request
     *
     * @param Request $request
     * @return array
     */
    private function getVnpayParams(Request $request): array
    {
        $vnpayParams = [];
        
        // Danh sách các tham số VNPay cần kiểm tra
        $vnpayKeys = [
            'vnp_Amount', 'vnp_BankCode', 'vnp_BankTranNo', 'vnp_CardType',
            'vnp_OrderInfo', 'vnp_PayDate', 'vnp_ResponseCode', 'vnp_TmnCode',
            'vnp_TransactionNo', 'vnp_TransactionStatus', 'vnp_TxnRef', 'vnp_SecureHash'
        ];

        foreach ($vnpayKeys as $key) {
            if ($request->has($key)) {
                $vnpayParams[$key] = $request->get($key);
            }
        }

        return $vnpayParams;
    }

    /**
     * Xử lý callback từ VNPay
     *
     * @param $order
     * @param array $vnpayParams
     * @param Request $request
     * @return void
     */
    private function handleVnpayCallback($order, array $vnpayParams, Request $request): void
    {
        try {
            // Log thông tin callback để debug
            \Log::info('=== VNPay Callback ===');
            \Log::info('Order Number: ' . $order->number);
            \Log::info('VNPay Params: ' . json_encode($vnpayParams, JSON_PRETTY_PRINT));
            \Log::info('======================');

            // Xác thực chữ ký (signature verification)
            if ($this->verifyVnpaySignature($vnpayParams, $request)) {
                
                // Kiểm tra mã phản hồi từ VNPay
                $responseCode = $vnpayParams['vnp_ResponseCode'] ?? '';
                $transactionStatus = $vnpayParams['vnp_TransactionStatus'] ?? '';
                
                if ($responseCode === '00' && $transactionStatus === '00') {
                    // Thanh toán thành công
                    $this->updateOrderPaymentSuccess($order, $vnpayParams);
                    
                    // Có thể thêm thông báo thành công vào session
                    session()->flash('payment_success', 'Thanh toán VNPay thành công!');
                    
                } else {
                    // Thanh toán thất bại
                    $this->updateOrderPaymentFailed($order, $vnpayParams);
                    
                    // Thêm thông báo lỗi vào session
                    session()->flash('payment_error', 'Thanh toán VNPay thất bại. Mã lỗi: ' . $responseCode);
                }
                
            } else {
                // Chữ ký không hợp lệ
                \Log::error('VNPay signature verification failed for order: ' . $order->number);
                session()->flash('payment_error', 'Xác thực thanh toán thất bại.');
            }
            
        } catch (Exception $e) {
            \Log::error('VNPay callback error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Xác thực chữ ký từ VNPay
     *
     * @param array $vnpayParams
     * @param Request $request
     * @return bool
     */
    private function verifyVnpaySignature(array $vnpayParams, Request $request): bool
    {
        $vnp_HashSecret = config('vnpay.hash_secret', "O8AL4CUFEUUKYTMQ2YVAAQ4S4P0NW1F6");
        $vnp_SecureHash = $vnpayParams['vnp_SecureHash'] ?? '';
        
        // Loại bỏ vnp_SecureHash khỏi dữ liệu để tính hash
        $inputData = $vnpayParams;
        unset($inputData['vnp_SecureHash']);
        
        // Sắp xếp theo thứ tự alphabet
        ksort($inputData);
        
        // Tạo chuỗi hash data
        $hashdata = "";
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }
        
        // Tính hash
        $secureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        
        return $secureHash === $vnp_SecureHash;
    }

    /**
     * Cập nhật trạng thái đơn hàng khi thanh toán thành công
     *
     * @param $order
     * @param array $vnpayParams
     * @return void
     */
    private function updateOrderPaymentSuccess($order, array $vnpayParams): void
    {
        try {
            // Cập nhật trạng thái đơn hàng thành "paid" hoặc "processing"
            StateMachineService::getInstance($order)->changeStatus(StateMachineService::PAID, 'VNPay payment successful', true);
            
            // Lưu thông tin giao dịch VNPay vào order (nếu có bảng payment_transactions)
            $this->saveVnpayTransaction($order, $vnpayParams, 'success');
            
        } catch (Exception $e) {
            \Log::error('Error updating order payment success: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật trạng thái đơn hàng khi thanh toán thất bại
     *
     * @param $order
     * @param array $vnpayParams
     * @return void
     */
    private function updateOrderPaymentFailed($order, array $vnpayParams): void
    {
        try {
            // Giữ nguyên trạng thái UNPAID hoặc chuyển sang CANCELLED
            // StateMachineService::getInstance($order)->changeStatus(StateMachineService::CANCELLED, 'VNPay payment failed', true);
            
            // Lưu thông tin giao dịch VNPay thất bại
            $this->saveVnpayTransaction($order, $vnpayParams, 'failed');
            
        } catch (Exception $e) {
            \Log::error('Error updating order payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Lưu thông tin giao dịch VNPay
     *
     * @param $order
     * @param array $vnpayParams
     * @param string $status
     * @return void
     */
    private function saveVnpayTransaction($order, array $vnpayParams, string $status): void
    {
        try {
            // Nếu bạn có bảng payment_transactions hoặc tương tự
            // Có thể lưu thông tin giao dịch ở đây
            
            // Ví dụ: Lưu vào notes của order
            $transactionInfo = [
                'payment_method' => 'VNPay',
                'transaction_no' => $vnpayParams['vnp_TransactionNo'] ?? '',
                'bank_tran_no' => $vnpayParams['vnp_BankTranNo'] ?? '',
                'bank_code' => $vnpayParams['vnp_BankCode'] ?? '',
                'card_type' => $vnpayParams['vnp_CardType'] ?? '',
                'pay_date' => $vnpayParams['vnp_PayDate'] ?? '',
                'amount' => ($vnpayParams['vnp_Amount'] ?? 0) / 100, // Chia 100 vì VNPay nhân với 100
                'response_code' => $vnpayParams['vnp_ResponseCode'] ?? '',
                'status' => $status,
                'processed_at' => now()
            ];
            
            // Cập nhật notes của order với thông tin giao dịch
            $currentNotes = $order->notes ?? '';
            $newNotes = $currentNotes . "\n\nVNPay Transaction: " . json_encode($transactionInfo, JSON_PRETTY_PRINT);
            
            OrderRepo::getInstance()->update($order->id, [
                'notes' => $newNotes
            ]);
            
        } catch (Exception $e) {
            \Log::error('Error saving VNPay transaction: ' . $e->getMessage());
        }
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
                'accountNo' => config('services.vietqr.account_no', '19038752078015'),
                'accountName' => config('services.vietqr.account_name', 'Do Giao Linh'),
                'acqId' => config('services.vietqr.bank_code', '970407'), // Mã ngân hàng
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