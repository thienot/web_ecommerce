<!-- Zalo Floating Button -->
<style>
.zalo-floating {
  position: fixed !important;
  bottom: 20px !important;
  right: 20px !important;
  z-index: 99999 !important;
  width: 60px !important;
  height: 60px !important;
  border-radius: 50% !important;
  background: linear-gradient(135deg, #0084ff, #00d4ff);
  border-radius: 50%;
  display: flex !important;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 20px rgba(0, 132, 255, 0.4);
  cursor: pointer !important;
  margin: 0 !important;
  padding: 0 !important;
  z-index: 1051; /* Cao hơn Bootstrap modal */
  transition: all 0.3s ease;
  text-decoration: none;
}

.zalo-floating:hover {
  transform: scale(1.1) !important;
  box-shadow: 0 6px 25px rgba(0, 104, 255, 0.6) !important;
}

.zalo-floating:active {
  transform: scale(0.95) !important;
}

.zalo-floating .zalo-icon {
  width: 32px !important;
  height: 32px !important;
  fill: white !important;
  flex-shrink: 0 !important;
}

/* Animation pulse effect */
.zalo-floating::before {
  content: '' !important;
  position: absolute !important;
  top: 50% !important;
  left: 50% !important;
  width: 100% !important;
  height: 100% !important;
  border-radius: 50% !important;
  background: rgba(0, 104, 255, 0.3) !important;
  transform: translate(-50%, -50%) !important;
  animation: zalo-pulse 2s infinite !important;
  pointer-events: none !important;
}

@keyframes zalo-pulse {
  0% {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
  }
  70% {
    transform: translate(-50%, -50%) scale(1.4);
    opacity: 0;
  }
  100% {
    transform: translate(-50%, -50%) scale(1.4);
    opacity: 0;
  }
}

/* Responsive design */
@media (max-width: 768px) {
  .zalo-floating {
    width: 50px !important;
    height: 50px !important;
    bottom: 15px !important;
    right: 15px !important;
  }
  
  .zalo-floating .zalo-icon {
    width: 26px !important;
    height: 26px !important;
  }
}

/* Tooltip khi hover */
.zalo-floating::after {
  content: 'Chat với chúng tôi qua Zalo';
  position: absolute;
  right: 70px;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0, 0, 0, 0.8);
  color: white;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 14px;
  white-space: nowrap;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
  pointer-events: none;
}

.zalo-floating:hover::after {
  opacity: 1;
  visibility: visible;
}

/* Ẩn tooltip trên mobile */
@media (max-width: 768px) {
  .zalo-floating::after {
    display: none !important;
  }
}
</style>

<div class="zalo-floating" onclick="openZaloChat()" title="Chat với chúng tôi qua Zalo">
  <svg class="zalo-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2C6.48 2 2 6.48 2 12c0 1.54.36 3.04 1.05 4.36L2 22l5.64-1.05C9.96 21.64 11.46 22 13 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.31 0-2.56-.31-3.65-.86L4 20l.86-4.35C4.31 14.56 4 13.31 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8z"/>
    <path d="M8.5 12.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5zm4 0c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5z"/>
  </svg>
</div>

<script>
function openZaloChat() {

  const phoneNumber = @json(system_setting('telephone'));
  const zaloId = parseInt(phoneNumber);
  
  // Tạo URL Zalo
  const zaloURL = `https://zalo.me/${zaloId}`;
  
  // Kiểm tra nếu là mobile thì mở app Zalo, desktop thì mở web
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  
  if (isMobile) {
    // Thử mở app Zalo trước
    window.location.href = `zalo://conversation?phone=${zaloId}`;
    
    // Nếu không có app thì mở web sau 1 giây
    setTimeout(() => {
      window.open(zaloURL, '_blank');
    }, 1000);
  } else {
    // Desktop: mở Zalo web
    window.open(zaloURL, '_blank');
  }
  
  // Google Analytics tracking (tùy chọn)
  if (typeof gtag !== 'undefined') {
    gtag('event', 'click', {
      'event_category': 'Contact',
      'event_label': 'Zalo Chat',
      'value': 1
    });
  }
}</script>