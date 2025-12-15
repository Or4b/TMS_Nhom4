document.addEventListener("DOMContentLoaded", function() {
    // 1. Auto set date (Chỉ chạy nếu có input date)
    const dateInput = document.getElementById('date');
    if (dateInput) {
        const today = new Date();
        const dateStr = today.toISOString().substr(0, 10);
        dateInput.value = dateStr;
        dateInput.min = dateStr;
    }

    // 2. Form validation (Chỉ chạy nếu là form tìm kiếm vé)
    // Chúng ta kiểm tra xem có tồn tại element 'origin' không trước khi gán sự kiện
    const originInput = document.getElementById('origin');
    const destinationInput = document.getElementById('destination');
    const searchForm = document.querySelector('form'); // Cẩn thận: Nếu trang login cũng có form, nó sẽ lấy form login

    // Chỉ gán sự kiện validate NẾU đang ở trang có input origin và destination
    if (originInput && destinationInput && searchForm) {
        searchForm.addEventListener('submit', function(e) {
            if (originInput.value === destinationInput.value) {
                e.preventDefault();
                alert('⚠️ Nơi đi và nơi đến không được trùng nhau!');
                originInput.focus();
                return false;
            }

            // Show loading
            const searchBtn = document.getElementById('search-btn');
            if (searchBtn) {
                searchBtn.disabled = true;
                searchBtn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Đang tìm kiếm...`;
            }
        });
    }

    // 3. Animate route cards (Chỉ chạy nếu có class .route-card)
    const routeCards = document.querySelectorAll('.route-card');
    if (routeCards.length > 0) {
        routeCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
                card.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
                card.style.boxShadow = '';
            });
        });
    }
});

/**
 * script.js
 * Xử lý các sự kiện Frontend và gọi AJAX tới api.php
 */

function showMessage(msg, isError = true) {
  const el = document.getElementById('msg');
  if (!el) return;
  el.style.color = isError ? 'red' : 'green'; // Hoặc class CSS tương ứng
  el.textContent = msg;
  el.style.display = 'block'; // Đảm bảo hiện lên
}

// Hàm gửi dữ liệu POST dạng Form
function postData(url, data) {
  const formData = new FormData();
  for (const k in data) formData.append(k, data[k]);
  return fetch(url, {
    method: 'POST',
    body: formData
  }).then(r => r.json());
}

// --- SR-1.1: REGISTER ---
function registerUser() {
  const username = document.getElementById('username').value.trim();
  const fullname = document.getElementById('fullname').value.trim();
  const email = document.getElementById('email').value.trim();
  const phone = document.getElementById('phone').value.trim();
  const password = document.getElementById('password').value;
  const confirm = document.getElementById('confirm_password').value;

  // Gửi thẳng lên API
  postData('api.php?action=register', {
    username, fullname, email, phone, password, confirm_password: confirm
  }).then(j => {
    if (j.status === 'ok') {
      showMessage(j.message, false);
      setTimeout(() => window.location = 'login.php', 1500);
    } else {
      // Hiển thị lỗi cụ thể từ PHP (VD: "Vui lòng nhập email")
      showMessage(j.message, true);
    }
  }).catch(e => showMessage('Lỗi kết nối server.'));
}

// --- SR-1.2: LOGIN ---
function loginUser() {
  const login = document.getElementById('login').value.trim();
  const password = document.getElementById('loginPassword').value;
  
  // Xóa đoạn kiểm tra rỗng tại đây để PHP xử lý
  postData('api.php?action=login', {login, password}).then(j => {
    if (j.status === 'ok') {
      showMessage('Đăng nhập thành công...', false);
      if (j.data) window.location = j.data;
    } else {
      showMessage(j.message);
    }
  }).catch(e => showMessage('Lỗi kết nối server.'));
}

// --- SR-1.3: REQUEST RESET ---
function requestPasswordReset() {
  const email = document.getElementById('resetEmail').value.trim();
  if (!email) { showMessage('Nhập email của bạn.'); return; }
  
  postData('api.php?action=request_reset', {email}).then(j => {
    if (j.status === 'ok') {
      // Yêu cầu Demo: Hiển thị link reset ngay trên màn hình để click
      if (j.data && j.data.reset_link) {
        const link = j.data.reset_link;
        // Hiển thị dạng HTML link để click luôn cho tiện test
        const el = document.getElementById('msg');
        el.style.color = '#2ecc71';
        el.innerHTML = `Link Demo (Click để test): <br> <a href="${link}">${link}</a>`;
      } else {
        showMessage(j.message, false);
      }
    } else showMessage(j.message);
  }).catch(e => showMessage('Lỗi kết nối.'));
}

// --- SR-1.3: CONFIRM RESET ---
function resetPassword() {
  const token = document.getElementById('token').value;
  const password = document.getElementById('newPassword').value;
  const confirm = document.getElementById('confirmPassword').value;

  if (!token || !password || !confirm) { showMessage('Thiếu dữ liệu.'); return; }

  postData('api.php?action=reset_password', {token, password, confirm_password: confirm}).then(j => {
    if (j.status === 'ok') {
      showMessage(j.message, false);
      setTimeout(() => window.location = 'login.php', 1500);
    } else showMessage(j.message);
  }).catch(e => showMessage('Lỗi kết nối.'));
}

// --- SR-1.4: LOAD PROVINCES (Trang chủ) ---
function loadProvinces() {
    const depSelect = document.getElementById('departureSelect');
    const destSelect = document.getElementById('destinationSelect');
    if(!depSelect || !destSelect) return;

    fetch('api.php?action=get_provinces')
        .then(res => res.json())
        .then(j => {
            if(j.status === 'ok') {
                let html = '<option value="">Chọn địa điểm</option>';
                j.data.forEach(p => {
                    html += `<option value="${p.id}">${p.name}</option>`;
                });
                depSelect.innerHTML = html;
                destSelect.innerHTML = html;
            }
        })
        .catch(err => console.error(err));
}

// --- SR-1.4: TOGGLE RETURN DATE ---
function toggleReturnDate(show) {
    const el = document.getElementById('returnDateDiv');
    const returnInput = document.querySelector('input[name="date_return"]');
    
    if(el) el.style.display = show ? 'block' : 'none';
    if(returnInput) returnInput.required = show; // Bắt buộc nhập nếu là khứ hồi
}

// --- SR-1.4: LOGOUT ---
function logout() {
    fetch('api.php?action=logout')
        .then(res => res.json())
        .then(j => {
            if(j.status === 'ok') window.location = 'login.php';
        });
}
