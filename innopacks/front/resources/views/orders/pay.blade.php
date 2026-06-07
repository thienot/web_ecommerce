@extends('layouts.app')
@section('body-class', 'page-checkout-success')

@section('content')

  <x-front-breadcrumb type="static" value="{{ front_route('orders.pay', ['number'=>$order->number]) }}" title="{{ $order->number }}"/>

  @hookinsert('order.pay.top')

  <div class="container">
    @error('error')
    <div class="alert alert-danger">
      {{ $message }}
    </div>
    @enderror

    @if(isset($error))
      {{ $error }}
    @endif

    <table class="table w-max-800 mx-auto mb-3 mb-md-5 checkout-success-table">
      <thead>
      <tr>
        <th>{{ __('front/order.order_number') }}</th>
        <th>{{ __('front/order.order_billing') }}</th>
        <th>{{ __('front/order.order_total') }}</th>
        <th>{{ __('front/order.order_status') }}</th>
      </tr>
      </thead>
      <tbody>
      <tr>
        <td>{{ $order->number }}</td>
        <td>{{ $order->billing_method_name }}</td>
        <td>{{ currency_format($order->total) }}</td>
        <td>{{ $order->status_format }}</td>
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

    <div class="d-flex flex-column justify-content-center w-max-800 mx-auto">
      @if(isset($payment_view))
        {!! $payment_view !!}
      @endif
    </div>
  </div>

  @hookinsert('order.pay.bottom')

@endsection

@push('footer')
  <script></script>
@endpush