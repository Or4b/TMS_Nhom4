/**
 * script.js
 * Xử lý các sự kiện Frontend và gọi AJAX tới api.php
 */

function showMessage(msg, isError = true) {
  const el = document.getElementById('msg');
  if (!el) return;
  el.style.color = isError ? '#e74c3c' : '#2ecc71';
  el.textContent = msg;
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

// --- SR-1.1: REGISTER (Cập nhật lấy Username) ---
function registerUser() {
  const username = document.getElementById('username').value.trim();
  const fullname = document.getElementById('fullname').value.trim();
  const email = document.getElementById('email').value.trim();
  const phone = document.getElementById('phone').value.trim();
  const password = document.getElementById('password').value;
  const confirm = document.getElementById('confirm_password').value;

  if (!username || !fullname || !email || !phone || !password || !confirm) {
    showMessage('Vui lòng điền đầy đủ thông tin.'); return;
  }

  // Gửi username lên API
  postData('api.php?action=register', {
    username, fullname, email, phone, password, confirm_password: confirm
  }).then(j => {
    if (j.status === 'ok') {
      showMessage(j.message, false);
      // Chuyển sang login sau 1.5s
      setTimeout(() => window.location = 'login.php', 1500);
    } else showMessage(j.message);
  }).catch(e => showMessage('Lỗi kết nối server.'));
}

// --- SR-1.2: LOGIN ---
function loginUser() {
  const login = document.getElementById('login').value.trim();
  const password = document.getElementById('loginPassword').value;
  
  // Xử lý Checkbox Ghi nhớ (Tùy chọn visual, chưa lưu cookie thực tế trong bài này)
  const remember = document.getElementById('remember').checked; 

  if (!login || !password) { 
      showMessage('Vui lòng nhập thông tin đăng nhập.'); return;
  }

  postData('api.php?action=login', {login, password}).then(j => {
    if (j.status === 'ok') {
      showMessage('Đăng nhập thành công...', false);
      // Điều hướng theo dữ liệu Role trả về từ API
      if (j.data) window.location = j.data;
    } else showMessage(j.message);
  }).catch(e => showMessage('Lỗi kết nối server.'));
}

// Toggle hiển thị mật khẩu
function togglePassword(inputId) {
  const el = document.getElementById(inputId);
  if (!el) return;
  el.type = el.type === 'password' ? 'text' : 'password';
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