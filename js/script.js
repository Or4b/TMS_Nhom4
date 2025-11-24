 document.addEventListener("DOMContentLoaded", function() {
        // Auto set today's date
        const today = new Date();
        const dateStr = today.toISOString().substr(0, 10);
        document.getElementById('date').value = dateStr;
        document.getElementById('date').min = dateStr;
        
        // Form validation
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const origin = document.getElementById('origin');
            const destination = document.getElementById('destination');
            
            if (origin.value === destination.value) {
                e.preventDefault();
                alert('⚠️ Nơi đi và nơi đến không được trùng nhau!');
                origin.focus();
                return false;
            }
            
            // Show loading
            const searchBtn = document.getElementById('search-btn');
            searchBtn.disabled = true;
            searchBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Đang tìm kiếm...
            `;
        });
        
        // Animate route cards on hover
        const routeCards = document.querySelectorAll('.route-card');
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
    });