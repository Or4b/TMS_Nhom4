// script.js

// Hàm gửi dữ liệu (Register/Login)
async function submitAuth(e, type) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const msgDiv = document.getElementById('message');

    try {
        const response = await fetch(`api.php?action=${type}`, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            msgDiv.className = 'success';
            msgDiv.innerText = result.message || 'Thành công!';
            if (result.redirect) {
                window.location.href = result.redirect;
            } else if (type === 'register') {
                setTimeout(() => window.location.href = 'login.php', 1500);
            }
        } else {
            msgDiv.className = 'error';
            msgDiv.innerText = result.message;
        }
    } catch (error) {
        console.error('Lỗi:', error);
    }
}

// Hàm Load Tỉnh Thành (Cho SCR-1.4)
async function loadProvinces() {
    try {
        const response = await fetch('api.php?action=get_provinces');
        const result = await response.json();

        if (result.status === 'success') {
            const depSelect = document.getElementById('departureSelect');
            const destSelect = document.getElementById('destinationSelect');
            
            if(!depSelect || !destSelect) return;

            let options = '<option value="">Chọn địa điểm</option>';
            result.data.forEach(province => {
                options += `<option value="${province.id}">${province.name}</option>`;
            });

            depSelect.innerHTML = options;
            destSelect.innerHTML = options;
        }
    } catch (error) {
        console.error('Không tải được danh sách tỉnh:', error);
    }
}

// Xử lý ẩn/hiện ngày về (SCR-1.4)
function toggleReturnDate(show) {
    const div = document.getElementById('returnDateDiv');
    if(div) div.style.display = show ? 'block' : 'none';
}

// Hàm Đăng xuất
async function logout() {
    await fetch('api.php?action=logout');
    window.location.href = 'login.php';
}