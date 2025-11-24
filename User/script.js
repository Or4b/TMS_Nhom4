// script.js
// Frontend JS: AJAX calls to api_user.php and small UI helpers.
// Naming: JS variables use camelCase.

function showMessage(msg, isError = true) {
  const el = document.getElementById('msg');
  if (!el) return;
  el.style.color = isError ? '#e74c3c' : '#1e7e34';
  el.textContent = msg;
}

// Helper: post form data (returns Promise resolved to JSON)
function postData(url, data) {
  const formData = new FormData();
  for (const k in data) formData.append(k, data[k]);
  return fetch(url, {
    method: 'POST',
    body: formData
  }).then(r => r.json());
}

// REGISTER
function registerUser() {
  const fullname = document.getElementById('fullname').value.trim();
  const email = document.getElementById('email').value.trim();
  const phone = document.getElementById('phone').value.trim();
  const password = document.getElementById('password').value;
  const confirm = document.getElementById('confirm_password').value;
  if (!fullname || !email || !phone || !password || !confirm) {
    showMessage('Vui lòng điền đầy đủ thông tin.');
    return;
  }
  postData('api_user.php?action=register', {
    fullname, email, phone, password, confirm_password: confirm
  }).then(j=>{
    if (j.status === 'ok') {
      showMessage(j.message, false);
      // redirect to login after 1.2s
      setTimeout(()=> window.location = 'login.php', 1200);
    } else showMessage(j.message);
  }).catch(e => showMessage('Lỗi kết nối.'));
}

// LOGIN
function loginUser() {
  const login = document.getElementById('login').value.trim();
  const password = document.getElementById('loginPassword').value;
  const role = document.querySelector('input[name="role"]:checked').value;
  if (!login || !password) { showMessage('Vui lòng nhập thông tin đăng nhập.'); return;}
  postData('api_user.php?action=login', {login, password, role}).then(j=>{
    if (j.status === 'ok') {
      // redirect to returned path
      if (j.data) window.location = j.data;
      else window.location = 'user_home.php';
    } else showMessage(j.message);
  }).catch(e=>showMessage('Lỗi kết nối.'));
}

// TOGGLE PASSWORD (simple)
function togglePassword(inputId) {
  const el = document.getElementById(inputId);
  if (!el) return;
  el.type = el.type === 'password' ? 'text' : 'password';
}

// REQUEST RESET
function requestPasswordReset() {
  const email = document.getElementById('resetEmail').value.trim();
  if (!email) { showMessage('Nhập email.'); return; }
  postData('api_user.php?action=request_reset', {email}).then(j=>{
    if (j.status === 'ok') {
      // For demo: if reset_link returned, show it
      if (j.data && j.data.reset_link) {
        showMessage('Demo link: ' + j.data.reset_link, false);
      } else {
        showMessage(j.message, false);
      }
    } else showMessage(j.message);
  }).catch(e=>showMessage('Lỗi kết nối.'));
}

// RESET PASSWORD (from token)
function resetPassword() {
  const token = document.getElementById('token').value;
  const password = document.getElementById('newPassword').value;
  const confirm = document.getElementById('confirmPassword').value;
  if (!token || !password || !confirm) { showMessage('Thiếu dữ liệu.'); return; }
  postData('api_user.php?action=reset_password', {token, password, confirm_password: confirm}).then(j=>{
    if (j.status === 'ok') {
      showMessage(j.message, false);
      setTimeout(()=> window.location = 'login.php', 1200);
    } else showMessage(j.message);
  }).catch(e=>showMessage('Lỗi kết nối.'));
}
