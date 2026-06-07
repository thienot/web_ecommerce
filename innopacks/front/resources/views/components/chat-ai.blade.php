<div id="inno-chatbot-app">
    <div class="chat-launcher">
        <svg width="28" height="28" viewBox="0 0 16 16" fill="white" xmlns="http://www.w3.org/2000/svg">
            <path d="M5 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0m4 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
            <path d="m2.165 15.803.02-.004c1.83-.363 2.948-.842 3.468-1.105A9 9 0 0 0 8 15c4.418 0 8-3.134 8-7s-3.582-7-8-7-8 3.134-8 7c0 1.76.743 3.37 1.97 4.6a10.4 10.4 0 0 1-.524 2.318l-.003.011a11 11 0 0 1-.244.637c-.079.186.074.394.273.362a22 22 0 0 0 .693-.125m.8-3.108a1 1 0 0 0-.287-.801C1.618 10.83 1 9.468 1 8c0-3.192 3.004-6 7-6s7 2.808 7 6-3.004 6-7 6a8 8 0 0 1-2.088-.272 1 1 0 0 0-.711.074c-.387.196-1.24.57-2.634.893a11 11 0 0 0 .398-2"/>
        </svg>
        <span style="color: white; font-size: 24px; display: none;">&times;</span>
    </div>

    <div class="chat-container" style="display: none;">
        <div class="chat-header">
            <span>{{ __('AI Assistant') }}</span>
            <span style="cursor: pointer;">&times;</span>
        </div>
        
        <div id="chat-content" class="chat-body"></div>

        <div class="chat-footer">
            <input type="text" placeholder="{{ __('Ask me anything...') }}">
            <button>{{ __('Send') }}</button>
        </div>
    </div>
</div>
<style>
    .chat-launcher { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; background: #0084ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.3); z-index: 9999; transition: 0.3s; }
    .chat-active { transform: rotate(90deg); background: #f44336; }
    .chat-container { position: fixed; bottom: 90px; right: 20px; width: 330px; height: 480px; background: white; border-radius: 15px; box-shadow: 0 8px 30px rgba(0,0,0,0.2); display: flex; flex-direction: column; z-index: 9999; overflow: hidden; border: 1px solid #eee; }
    .chat-header { background: #0084ff; color: white; padding: 15px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
    .chat-body { flex: 1; overflow-y: auto; padding: 15px; background: #f0f2f5; display: flex; flex-direction: column; gap: 10px; }
    .msg-wrapper { display: flex; width: 100%; }
    .msg-wrapper.customer { justify-content: flex-end; }
    .msg-wrapper.bot { justify-content: flex-start; }
    .msg-text { max-width: 80%; padding: 10px 14px; border-radius: 18px; font-size: 14px; line-height: 1.4; }
    .customer .msg-text { background: #0084ff; color: white; border-bottom-right-radius: 4px; }
    .bot .msg-text { background: white; color: #333; border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .chat-footer { padding: 10px; background: white; display: flex; border-top: 1px solid #eee; gap: 5px; }
    .chat-footer input { flex: 1; border: 1px solid #ddd; border-radius: 20px; padding: 8px 15px; outline: none; }
    .chat-footer button { border: none; background: none; color: #0084ff; font-weight: bold; cursor: pointer; }
    .typing { color: #888; font-weight: bold; }
</style>

  <!-- ==================== CHATBOT THUẦN JAVASCRIPT ==================== -->
  <script>
  document.addEventListener('DOMContentLoaded', function () {
      
      const container = document.getElementById('inno-chatbot-app');
      if (!container) return;

      let isOpen = false;
      let isLoading = false;
      let messages = [];
      
      const guestToken = localStorage.getItem('chat_token') || ('gt_' + Math.random().toString(36).substr(2, 9));
      if (!localStorage.getItem('chat_token')) {
          localStorage.setItem('chat_token', guestToken);
      }

      const chatApi = @json([
          'ask' => front_route('chatbot.ask'),
          'history' => front_route('chatbot.history')
      ]);

      function toggleChat() {
          isOpen = !isOpen;
          render();
          if (isOpen && messages.length === 0) {
              loadHistory();
          }
      }

      function scrollToBottom() {
          setTimeout(() => {
              const chatBody = document.getElementById('chat-content');
              if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
          }, 100);
      }

      async function loadHistory() {
          try {
              const url = new URL(chatApi.history);
              url.searchParams.append('guest_token', guestToken);
              const res = await fetch(url.toString());
              if (res.ok) {
                  const data = await res.json();
                  messages = data || [];
                  render();
                  scrollToBottom();
              }
          } catch (err) {}
      }

      async function sendMsg() {
          const input = container.querySelector('input');
          const text = input.value.trim();
          
          if (!text || isLoading) return;

          // Thêm tin nhắn người dùng
          messages.push({ sender: 'customer', message: text });
          
          isLoading = true;
          render();
          scrollToBottom();

          // **Xóa input ngay lập tức**
          input.value = '';

          try {
              const res = await fetch(chatApi.ask, {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                  },
                  body: JSON.stringify({
                      message: text,
                      guest_token: guestToken
                  })
              });

              const data = await res.json();
              messages.push({ sender: 'bot', message: data.message || 'Cảm ơn bạn!' });
          } catch (err) {
              messages.push({ sender: 'bot', message: 'Sorry, I am having trouble. Please try again.' });
          } finally {
              isLoading = false;
              render();
              scrollToBottom();
          }
      }

      function render() {
          // Launcher
          const launcher = container.querySelector('.chat-launcher');
          if (launcher) {
              launcher.classList.toggle('chat-active', isOpen);
              const svg = launcher.querySelector('svg');
              const close = launcher.querySelector('span');
              if (svg) svg.style.display = isOpen ? 'none' : 'block';
              if (close) close.style.display = isOpen ? 'block' : 'none';
          }

          // Chat container
          const chatContainer = container.querySelector('.chat-container');
          if (chatContainer) chatContainer.style.display = isOpen ? 'flex' : 'none';

          // Messages
          const chatBody = document.getElementById('chat-content');
          if (chatBody) {
              let html = '';
              messages.forEach(msg => {
                  html += `<div class="msg-wrapper ${msg.sender}"><div class="msg-text">${msg.message}</div></div>`;
              });
              if (isLoading) {
                  html += `<div class="msg-wrapper bot"><div class="msg-text typing">...</div></div>`;
              }
              chatBody.innerHTML = html;
          }
      }

      function attachEvents() {
          container.querySelector('.chat-launcher').addEventListener('click', toggleChat);
          
          const closeBtn = container.querySelector('.chat-header span[style*="cursor"]');
          if (closeBtn) closeBtn.addEventListener('click', toggleChat);

          const input = container.querySelector('input');
          if (input) {
              input.addEventListener('keyup', e => {
                  if (e.key === 'Enter') sendMsg();
              });
          }

          const sendBtn = container.querySelector('button');
          if (sendBtn) sendBtn.addEventListener('click', sendMsg);
      }

      attachEvents();
      render();
  });
  </script>