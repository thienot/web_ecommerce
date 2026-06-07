<?php

namespace InnoShop\Front\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use InnoShop\Common\Repositories\OrderRepo;

class PaymentController extends Controller
{
    /**
     * Xử lý thanh toán VNPay
     */
    public function vnpay_payment(Request $request)
    {
        try {
            $data = $request->all();
            
            // Lấy thông tin đơn hàng từ request
            $orderNumber = $data['order_number'] ?? null;
            if (!$orderNumber) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Không tìm thấy thông tin đơn hàng'
                ], 400);
            }
            
            $order = OrderRepo::getInstance()->builder(['number' => $orderNumber])->first();
            if (!$order) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Đơn hàng không tồn tại'
                ], 404);
            }


            
            // Sử dụng helper currency_payment_data để lấy thông tin tiền tệ
            $paymentData = currency_payment_data($order->total);
            
            // DEBUG: In ra dữ liệu payment để kiểm tra
            \Log::info('=== DEBUG PAYMENT DATA ===');

            \Log::info('linh Payment data: ' . json_encode($paymentData, JSON_PRETTY_PRINT));
            \Log::info('linh Payment data structure:', $paymentData);
            \Log::info('=============================');
            
            // Cấu hình VNPay
            $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
            
            // THAY ĐỔI: Redirect trực tiếp đến trang success với order_number
            $vnp_Returnurl = url('/vi/checkout/success?order_number=' . $order->number);
            
            $vnp_TmnCode = config('vnpay.tmn_code', "TPXTLAS7");
            $vnp_HashSecret = config('vnpay.hash_secret', "O8AL4CUFEUUKYTMQ2YVAAQ4S4P0NW1F6");
            
            $vnp_TxnRef = $order->number;
            $vnp_OrderInfo = 'Thanh toán đơn hàng #' . $order->number;
            $vnp_OrderType = 'billpayment';
            $vnp_Amount = ($paymentData['amount'] ?? 0) * 100;
            $vnp_Locale = 'vn';
            $vnp_IpAddr = $request->ip();

            $inputData = array(
                "vnp_Version" => "2.1.0",
                "vnp_TmnCode" => $vnp_TmnCode,
                "vnp_Amount" => $vnp_Amount,
                "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode" => $paymentData['currency_code'] ?? 'USD',
                "vnp_IpAddr" => $vnp_IpAddr,
                "vnp_Locale" => $vnp_Locale,
                "vnp_OrderInfo" => $vnp_OrderInfo,
                "vnp_OrderType" => $vnp_OrderType,
                "vnp_ReturnUrl" => $vnp_Returnurl,
                "vnp_TxnRef" => $vnp_TxnRef,
            );

            if (isset($data['vnp_BankCode']) && $data['vnp_BankCode'] != "") {
                $inputData['vnp_BankCode'] = $data['vnp_BankCode'];
            }

            ksort($inputData);
            $query = "";
            $i = 0;
            $hashdata = "";
            
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashdata .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
                $query .= urlencode($key) . "=" . urlencode($value) . '&';
            }
            
            $vnp_Url = $vnp_Url . "?" . $query;
            if (isset($vnp_HashSecret)) {
                $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
                $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
            }

            return response()->json([
                'success' => true,
                'message' => 'Chuyển hướng đến VNPay',
                'data' => ['redirect_url' => $vnp_Url]
            ]);

        } catch (\Exception $e) {
            // Log lỗi để debug
            \Log::error('VNPay Payment Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xử lý callback từ VNPay - Có thể giữ lại để xử lý webhook (nếu cần)
     * hoặc để xử lý các trường hợp đặc biệt
     */
    public function vnpay_return(Request $request)
    {
        try {
            $vnp_HashSecret = config('vnpay.hash_secret', "O8AL4CUFEUUKYTMQ2YVAAQ4S4P0NW1F6");
            $inputData = $request->all();
            $vnp_SecureHash = $inputData['vnp_SecureHash'];
            unset($inputData['vnp_SecureHash']);
            ksort($inputData);
            
            $hashData = "";
            $i = 0;
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashData .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
            }

            $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
            
            if ($secureHash == $vnp_SecureHash) {
                $orderNumber = $inputData['vnp_TxnRef'];
                $order = OrderRepo::getInstance()->builder(['number' => $orderNumber])->first();
                
                if ($inputData['vnp_ResponseCode'] == '00') {
                    // Thanh toán thành công - Cập nhật trạng thái đơn hàng
                    if ($order) {
                        $order->update(['payment_status' => 'paid']);
                    }
                    
                    // Log thông tin thanh toán thành công
                    \Log::info('VNPay Payment Success for order: ' . $orderNumber);
                    
                    return redirect()->route('checkout.success', ['order_number' => $orderNumber])
                        ->with('success', 'Thanh toán thành công!');
                } else {
                    // Thanh toán thất bại
                    return redirect()->route('checkout.index')
                        ->with('error', 'Thanh toán thất bại!');
                }
            } else {
                return redirect()->route('checkout.index')
                    ->with('error', 'Chữ ký không hợp lệ!');
            }
        } catch (\Exception $e) {
            \Log::error('VNPay Return Error: ' . $e->getMessage());
            
            return redirect()->route('checkout.index')
                ->with('error', 'Có lỗi xảy ra trong quá trình xử lý thanh toán!');
        }
    }

    /**
     * THÊM MỚI: Xử lý trang success - kiểm tra và xác nhận thanh toán
     */
/**
 * SECURE VERSION: Xử lý trang success với validation kép
 */
public function checkout_success(Request $request)
{
    try {
        $orderNumber = $request->get('order_number');
        
        if (!$orderNumber) {
            return redirect()->route('checkout.index')
                ->with('error', 'Không tìm thấy thông tin đơn hàng!');
        }
        
        // Lấy thông tin đơn hàng
        $order = OrderRepo::getInstance()->builder(['number' => $orderNumber])->first();
        
        if (!$order) {
            return redirect()->route('checkout.index')
                ->with('error', 'Đơn hàng không tồn tại!');
        }
        
        // CASE 1: Đơn hàng đã được xác nhận thanh toán (từ vnpay_return hoặc IPN)
        if ($order->payment_status === 'paid') {
            \Log::info('Displaying success page for already paid order: ' . $orderNumber);
            return view('checkout.success', compact('order'));
        }
        
        // CASE 2: Chưa thanh toán, kiểm tra có phải response từ VNPay không
        if ($this->isValidVNPayResponse($request)) {
            // Xác nhận thanh toán và cập nhật trạng thái
            $order->update([
                'payment_status' => 'paid',
                'paid_at' => now()
            ]);
            
            \Log::info('Order payment confirmed via success page from VNPay: ' . $orderNumber);
            return view('checkout.success', compact('order'));
        }
        
        // CASE 3: Truy cập không hợp lệ (không có VNPay params và chưa thanh toán)
        \Log::warning('Invalid access to success page for unpaid order: ' . $orderNumber);
        return redirect()->route('checkout.index')
            ->with('error', 'Đơn hàng chưa được thanh toán! Vui lòng thực hiện thanh toán.');
            
    } catch (\Exception $e) {
        \Log::error('Checkout Success Error: ' . $e->getMessage());
        
        return redirect()->route('checkout.index')
            ->with('error', 'Có lỗi xảy ra!');
    }
}

/**
 * Kiểm tra tính hợp lệ của response từ VNPay
 */
private function isValidVNPayResponse(Request $request)
{
    // Kiểm tra có các parameters cần thiết từ VNPay không
    $requiredParams = ['vnp_ResponseCode', 'vnp_TxnRef', 'vnp_SecureHash'];
    
    foreach ($requiredParams as $param) {
        if (!$request->has($param)) {
            return false;
        }
    }
    
    // Kiểm tra response code thành công
    if ($request->get('vnp_ResponseCode') !== '00') {
        return false;
    }
    
    // Verify chữ ký VNPay
    $vnp_HashSecret = config('vnpay.hash_secret', "O8AL4CUFEUUKYTMQ2YVAAQ4S4P0NW1F6");
    $inputData = $request->all();
    $vnp_SecureHash = $inputData['vnp_SecureHash'];
    unset($inputData['vnp_SecureHash']);
    unset($inputData['order_number']); // Remove non-VNPay parameter
    
    ksort($inputData);
    
    $hashData = "";
    $i = 0;
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashData .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
    }
    
    $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
    
    return $secureHash === $vnp_SecureHash;
}
}