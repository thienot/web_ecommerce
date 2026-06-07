@extends('layouts.app')
@section('body-class', 'page-checkout-success')

@section('content')

  @if($order)
    <x-front-breadcrumb type="order" :value="$order"/>
  @endif

  @hookinsert('checkout.success.top')

  <div class="container">
    <div class="checkout-success-box">
      @if($order)
        <div class="order-success-icon"><img src="{{ asset('/images/icons/order-success.svg') }}" class="img-fluid"></div>
        
        {{-- Hiển thị thông báo thanh toán --}}
        @if(session('payment_success'))
          <div class="alert alert-success mb-3">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('payment_success') }}
          </div>
        @endif
        
        @if(session('payment_error'))
          <div class="alert alert-danger mb-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            {{ session('payment_error') }}
          </div>
        @endif
        
        <div class="checkout-success-title">
          <span>Cảm ơn bạn. Đơn hàng của bạn đã được nhận. Sau khoảng 3-6 giờ chúng tôi sẽ cập nhật trạng thái đơn hàng, nếu không vui lòng liên hệ với chúng tôi.</span>
        </div>
        
        <table class="table w-max-700 mx-auto mb-3 mb-md-5 checkout-success-table">
          <thead>
          <tr>
            <th>Mã đơn hàng</th>
            <th>Ngày đặt</th>
            <th>Tổng đơn</th>
            <th>Trạng thái</th>
          </tr>
          </thead>
          <tbody>
          <tr>
            <td>{{ $order->number }}</td>
            <td>{{ $order->created_at->format('Y-m-d') }}</td>
            <td>{{ currency_format($order->total) }}</td>
            <td>{{ $order->status }}</td>
          </tr>
          </tbody>
        </table>

        {{-- Hiển thị QR Code thanh toán --}}
        @if($qr_code_image)
          <div class="qr-payment-section mb-4">
            <h5 class="mb-3 text-center">
              <i class="fas fa-qrcode me-2"></i>
              Thanh toán bằng QR Code
            </h5>
            <div class="card">
              <div class="card-body text-center">
                <div class="qr-code-container mb-3">
                  <img src="{{ $qr_code_image }}" 
                       alt="QR Code thanh toán" 
                       class="img-fluid qr-code-image"
                       style="max-width: 300px; border: 1px solid #ddd; border-radius: 8px;">
                </div>
                <p class="text-muted mb-2">
                  <i class="fas fa-mobile-alt me-1"></i>
                  Quét mã QR bằng ứng dụng ngân hàng để thanh toán
                </p>
                <div class="payment-info">
                  <p><strong>Số tiền:</strong> {{ currency_format($order->total) }}</p>
                  <p><strong>Nội dung:</strong> Thanh toan don hang #{{ $order->number }}</p>
                </div>
                
                {{-- Nút tải QR Code --}}
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="downloadQRCode()">
                  <i class="fas fa-download me-1"></i>
                  Tải QR Code
                </button>
              </div>
            </div>
          </div>
        @endif

        {{-- Hiển thị thông tin thanh toán VNPay nếu có --}}
        @if(isset($vnpay_result) && !empty($vnpay_result))
          <div class="vnpay-payment-info mb-4">
            <h5 class="mb-3">
              <i class="fas fa-credit-card me-2"></i>
              Thông tin thanh toán VNPay
            </h5>
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <p><strong>Mã giao dịch VNPay:</strong> {{ $vnpay_result['vnp_TransactionNo'] ?? 'N/A' }}</p>
                    <p><strong>Mã giao dịch ngân hàng:</strong> {{ $vnpay_result['vnp_BankTranNo'] ?? 'N/A' }}</p>
                    <p><strong>Ngân hàng:</strong> {{ $vnpay_result['vnp_BankCode'] ?? 'N/A' }}</p>
                  </div>
                  <div class="col-md-6">
                    <p><strong>Loại thẻ:</strong> {{ $vnpay_result['vnp_CardType'] ?? 'N/A' }}</p>
                    <p><strong>Thời gian thanh toán:</strong> 
                      @if(isset($vnpay_result['vnp_PayDate']))
                        {{ \Carbon\Carbon::createFromFormat('YmdHis', $vnpay_result['vnp_PayDate'])->format('d/m/Y H:i:s') }}
                      @else
                        N/A
                      @endif
                    </p>
                    <p><strong>Số tiền:</strong> 
                      @if(isset($vnpay_result['vnp_Amount']))
                        {{ number_format($vnpay_result['vnp_Amount'] / 100, 0, ',', '.') }} VNĐ
                      @else
                        N/A
                      @endif
                    </p>
                  </div>
                </div>
                
                {{-- Hiển thị trạng thái thanh toán --}}
                <div class="payment-status mt-3">
                  @php
                    $responseCode = $vnpay_result['vnp_ResponseCode'] ?? '';
                    $transactionStatus = $vnpay_result['vnp_TransactionStatus'] ?? '';
                  @endphp
                  
                  @if($responseCode === '00' && $transactionStatus === '00')
                    <div class="alert alert-success">
                      <i class="fas fa-check-circle me-2"></i>
                      <strong>Thanh toán thành công!</strong>
                    </div>
                  @else
                    <div class="alert alert-danger">
                      <i class="fas fa-times-circle me-2"></i>
                      <strong>Thanh toán thất bại!</strong>
                      <br>
                      <small>Mã lỗi: {{ $responseCode }}</small>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          </div>
        @endif

        <div class="checkout-success-btns d-flex flex-column justify-content-center w-max-400 mx-auto">
          @if(current_customer())
            <a href="{{ account_route('orders.number_show', ['number'=>$order->number]) }}"
               class="btn btn-lg btn-primary mb-3">View Order</a>
          @else
            <a href="{{ front_route('orders.number_show', ['number'=>$order->number]) }}"
               class="btn btn-lg btn-primary mb-3">View Order</a>
          @endif
          <a href="{{ front_route('home.index') }}" class="btn btn-lg btn-outline-primary">Continue Shopping</a>
        </div>
      @else
        <div class="no-order-message">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No order found.
          </div>
          <a href="{{ front_route('home.index') }}" class="btn btn-primary">Go to Homepage</a>
        </div>
      @endif
    </div>
  </div>
  @hookinsert('checkout.success.bottom')
@endsection

@push('footer')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Auto hide alerts after 5 seconds
      setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
          if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
              alert.remove();
            }, 500);
          }
        });
      }, 5000);
    });

    // Function to download QR Code
    function downloadQRCode() {
      const qrImage = document.querySelector('.qr-code-image');
      if (qrImage) {
        const link = document.createElement('a');
        link.download = 'qr-code-payment.png';
        link.href = qrImage.src;
        link.click();
      }
    }

    // Optional: Copy QR Code to clipboard
    function copyQRCode() {
      const qrImage = document.querySelector('.qr-code-image');
      if (qrImage) {
        // Convert image to canvas and copy
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        img.onload = function() {
          canvas.width = img.width;
          canvas.height = img.height;
          ctx.drawImage(img, 0, 0);
          
          canvas.toBlob(function(blob) {
            navigator.clipboard.write([
              new ClipboardItem({ 'image/png': blob })
            ]).then(function() {
              alert('QR Code đã được sao chép!');
            });
          });
        };
        img.src = qrImage.src;
      }
    }
  </script>

  <style>
    .qr-payment-section .card {
      border: 1px solid #e3e6f0;
      box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    
    .qr-code-image {
      transition: transform 0.2s ease;
    }
    
    .qr-code-image:hover {
      transform: scale(1.05);
    }
    
    .payment-info p {
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
      .qr-code-image {
        max-width: 250px !important;
      }
    }
  </style>
@endpush