document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.verify-page')) return;

    const otpInputs = document.querySelectorAll('.otp-input');
    const verifyForm = document.getElementById('verifyForm');
    const timerElement = document.getElementById('timer');
    const resendLink = document.getElementById('resendOtp');
    const messageContainer = document.getElementById('verification-message');

    // Khởi tạo thời gian
    const initialExpire = parseInt(timerElement.dataset.expire) || 0;
    let timeLeft = Math.max(Math.floor(initialExpire - Date.now()/1000), 0);

    // Xử lý nhập OTP
    const handleOtpInput = (e, index) => {
        e.target.value = e.target.value.replace(/\D/g, '');

        // Tự động chuyển focus
        if (e.target.value.length === 1 && index < 5) {
            otpInputs[index + 1].focus();
        }
    };

    // Gắn sự kiện input và keydown cho từng ô OTP
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => handleOtpInput(e, index));
        input.addEventListener('keydown', (e) => {
            // Xử lý Backspace
            if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
    });

    // Bộ đếm ngược
    const startTimer = () => {
        const timerInterval = setInterval(() => {
            timeLeft = Math.max(timeLeft - 1, 0);

            const minutes = Math.floor(timeLeft / 60).toString().padStart(2, '0');
            const seconds = (timeLeft % 60).toString().padStart(2, '0');
            timerElement.textContent = `${minutes}:${seconds}`;

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                resendLink.style.display = 'inline-block';
            }
        }, 1000);
    };

    // Xử lý submit form
    const handleSubmit = async (e) => {
        e.preventDefault();

        // Validate input
        const otpCode = Array.from(otpInputs).map(i => i.value).join('');
        if (otpCode.length !== 6) {
            showMessage('Vui lòng nhập đủ 6 chữ số', 'error');
            return;
        }

        try {
            const response = await fetch('verify_process.php', {
                method: 'POST',
                body: new FormData(verifyForm)
            });

            const result = await response.json();

            if (result.status === 'success') {
                showMessage(result.message, 'success');
                setTimeout(() => window.location.href = result.redirect, 1500);
            } else {
                showMessage(result.message, 'error');
                otpInputs.forEach(i => i.value = '');
                otpInputs[0].focus();
            }
        } catch (error) {
            showMessage('Lỗi kết nối server', 'error');
        }
    };

    // Hiển thị thông báo
    const showMessage = (message, type) => {
        messageContainer.innerHTML = `
            <div class="alert alert-${type}">
                ${message}
            </div>
        `;
        setTimeout(() => messageContainer.innerHTML = '', 3000);
    };

    // Gắn sự kiện submit cho form xác thực
    verifyForm.addEventListener('submit', handleSubmit);
    // Bắt đầu bộ đếm thời gian
    startTimer();
});

// Autocomplete
document.addEventListener('DOMContentLoaded', () => {
    // Hàm khởi tạo tính năng tự động hoàn thành
    const initAutocomplete = () => {
        const destinationInput = document.getElementById('destination');
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.className = 'autocomplete-suggestions';
        destinationInput.parentNode.appendChild(suggestionsContainer);

        let timeoutId;

        // Hàm hiển thị các gợi ý
        const showSuggestions = (items) => {
            suggestionsContainer.innerHTML = items
                .map(item => `<div class="suggestion-item">${item}</div>`)
                .join('');

            suggestionsContainer.style.display = items.length ? 'block' : 'none';
        };

        // Lắng nghe sự kiện nhập liệu vào ô input địa điểm
        destinationInput.addEventListener('input', async (e) => {
            clearTimeout(timeoutId);
            const query = e.target.value.trim();

            // Nếu độ dài truy vấn nhỏ hơn 2 ký tự, ẩn gợi ý và thoát
            if(query.length < 2) {
                showSuggestions([]);
                return;
            }

            // Thiết lập timeout để gọi API sau một khoảng thời gian ngắn (tránh gọi liên tục khi gõ)
            timeoutId = setTimeout(async () => {
                try {
                    const response = await fetch(`api/autocomplete.php?q=${encodeURIComponent(query)}`);
                    const data = await response.json();
                    showSuggestions(data);
                } catch(error) {
                    console.error('Lỗi tự động hoàn thành:', error);
                }
            }, 300);
        });

        // Xử lý sự kiện click vào một gợi ý
        suggestionsContainer.addEventListener('click', (e) => {
            if(e.target.classList.contains('suggestion-item')) {
                destinationInput.value = e.target.textContent;
                suggestionsContainer.style.display = 'none';
            }
        });

        // Ẩn gợi ý khi click ra ngoài ô input
        document.addEventListener('click', (e) => {
            if(!destinationInput.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });
    };

    // Hàm kiểm tra và thiết lập ràng buộc cho các ô chọn ngày
    const validateDates = () => {
        const checkin = document.getElementById('checkin');
        const checkout = document.getElementById('checkout');

        // Đặt ngày tối thiểu cho ngày nhận phòng là ngày hiện tại
        checkin.min = new Date().toISOString().split('T')[0];

        // Lắng nghe sự kiện thay đổi ngày nhận phòng
        checkin.addEventListener('change', () => {
            const checkinDate = new Date(checkin.value);
            // Đặt ngày tối thiểu cho ngày trả phòng là ngày sau ngày nhận phòng
            checkinDate.setDate(checkinDate.getDate() + 1);
            checkout.min = checkinDate.toISOString().split('T')[0];

            // Nếu ngày trả phòng được chọn trước ngày tối thiểu, đặt lại giá trị
            if(new Date(checkout.value) < checkinDate) {
                checkout.value = checkout.min;
            }
        });
    };

    // Khởi tạo các tính năng nếu phần tử tồn tại trên trang
    if(document.getElementById('destination')) {
        initAutocomplete();
        validateDates();
    }
});

/* Thêm script xử lý cho booking.php */
document.addEventListener('DOMContentLoaded', () => {
    const bookingForm = document.getElementById('bookingForm');
    if (!bookingForm) return;

    bookingForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(bookingForm);
        const messageContainer = document.getElementById('booking-message');
        messageContainer.innerHTML = ''; // Clear message

        try {
            const response = await fetch('payment.php', {
                method: 'POST',
                body: formData,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const text = await response.text();
            messageContainer.innerHTML = text;

        } catch (error) {
            messageContainer.innerHTML = `<div class="error">Lỗi: ${error.message}</div>`;
        }
    });
});


document.addEventListener('DOMContentLoaded', function() {

    // --- DETAIL.PHP: Update min checkout date & Form validation ---
    const detailPageForm = document.querySelector('.view-rooms-form');
    if (detailPageForm) {
        const checkinInput = detailPageForm.querySelector('#checkin');
        const checkoutInput = detailPageForm.querySelector('#checkout');

        if (checkinInput && checkoutInput) {
            // Set initial min for checkout based on checkin
            const initialCheckinDate = new Date(checkinInput.value);
            const initialNextDay = new Date(initialCheckinDate.setDate(initialCheckinDate.getDate() + 1));
            checkoutInput.min = initialNextDay.toISOString().split('T')[0];


            checkinInput.addEventListener('change', function() {
                const checkinDate = new Date(this.value);
                // Reset date to avoid issues with setDate modifying original
                const nextDay = new Date(this.value);
                nextDay.setDate(checkinDate.getDate() + 1);

                const nextDayString = nextDay.toISOString().split('T')[0];
                checkoutInput.min = nextDayString;
                // If checkout is earlier than new checkin+1, update checkout
                if (new Date(checkoutInput.value) <= new Date(this.value)) {
                    checkoutInput.value = nextDayString;
                }
            });
        }
        // Basic validation before submitting from detail page
        detailPageForm.addEventListener('submit', function(e) {
            if (checkinInput && checkoutInput && new Date(checkoutInput.value) <= new Date(checkinInput.value)) {
                alert('Ngày trả phòng phải sau ngày nhận phòng.');
                e.preventDefault();
            }
        });
    }


    // --- BOOKING.PHP: Calculate estimated price ---
    const bookingFormPage = document.getElementById('bookingFormPage');
    if (bookingFormPage) {
        const roomSelectBooking = bookingFormPage.querySelector('#room_id');
        const quantityInputBooking = bookingFormPage.querySelector('#quantity');
        const priceDisplayBooking = bookingFormPage.querySelector('#estimated_total_price_booking');
        const nightsTextEl = document.getElementById('booking_nights'); // Lấy element chứa số đêm
        let nightsBooking = 0;

        if(nightsTextEl) {
            nightsBooking = parseInt(nightsTextEl.textContent || '0', 10);
        }


        function calculateBookingPrice() {
            if (!roomSelectBooking || !quantityInputBooking || !priceDisplayBooking ) {
                if (priceDisplayBooking) priceDisplayBooking.textContent = 'Vui lòng chọn phòng và số lượng';
                return;
            }
            if (nightsBooking <= 0) {
                 if (priceDisplayBooking) priceDisplayBooking.textContent = 'Số đêm không hợp lệ';
                return;
            }


            const selectedOption = roomSelectBooking.options[roomSelectBooking.selectedIndex];
            const roomPrice = parseFloat(selectedOption.dataset.price);
            const quantity = parseInt(quantityInputBooking.value, 10);

            if (selectedOption.value && roomPrice > 0 && quantity > 0) {
                const total = roomPrice * quantity * nightsBooking;
                priceDisplayBooking.textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(total);
            } else if (selectedOption.value) {
                priceDisplayBooking.textContent = 'Nhập số lượng phòng hợp lệ';
            } else {
                priceDisplayBooking.textContent = 'Vui lòng chọn loại phòng';
            }
        }

        if (roomSelectBooking) roomSelectBooking.addEventListener('change', calculateBookingPrice);
        if (quantityInputBooking) quantityInputBooking.addEventListener('input', calculateBookingPrice);

        // Initial calculation if values are pre-filled (e.g., after form error)
        if (nightsBooking > 0) { // Chỉ tính nếu số đêm hợp lệ
           calculateBookingPrice();
        } else if (priceDisplayBooking) {
            priceDisplayBooking.textContent = 'Kiểm tra lại ngày nhận/trả phòng';
        }
    }


    // --- PAYMENT.PHP: Client-side validation for Visa form ---
    const actualPaymentForm = document.getElementById('actualPaymentForm');
    if (actualPaymentForm) {
        // Lấy phương thức thanh toán từ biến JavaScript toàn cục (PENDING_BOOKING_DATA)
        // được khai báo trong file payment.php
        const paymentMethod = (typeof PENDING_BOOKING_DATA !== 'undefined' && PENDING_BOOKING_DATA.chosenPaymentMethod)
                                ? PENDING_BOOKING_DATA.chosenPaymentMethod
                                : null;

        if (paymentMethod) { // Chỉ thêm event listener nếu có paymentMethod
            actualPaymentForm.addEventListener('submit', function(event) {
                if (paymentMethod === 'visa') {
                    const cardNumberInput = actualPaymentForm.querySelector('#card_number_visa');
                    const cardExpiryInput = actualPaymentForm.querySelector('#card_expiry_visa');
                    const cardCvcInput = actualPaymentForm.querySelector('#card_cvc_visa');
                    const cardHolderNameInput = actualPaymentForm.querySelector('#card_holder_name_visa');

                    const cardNumber = cardNumberInput ? cardNumberInput.value.trim() : '';
                    const cardExpiry = cardExpiryInput ? cardExpiryInput.value.trim() : '';
                    const cardCvc = cardCvcInput ? cardCvcInput.value.trim() : '';
                    const cardHolderName = cardHolderNameInput ? cardHolderNameInput.value.trim() : '';

                    let visaValid = true;
                    let alertMessage = '';

                    // Xóa các thông báo lỗi cũ (nếu có)
                    document.querySelectorAll('.payment-form-error-text').forEach(el => el.remove());


                    if (!cardNumber || !/^(\d{4} ?){3}\d{4}$/.test(cardNumber.replace(/\s/g, ''))) {
                        alertMessage += 'Số thẻ Visa không hợp lệ (phải đủ 16 chữ số).\n';
                        visaValid = false;
                        displayFieldError(cardNumberInput, 'Số thẻ Visa không hợp lệ (16 chữ số).');
                    }
                    if (!cardExpiry || !/^(0[1-9]|1[0-2])\/\d{2}$/.test(cardExpiry)) {
                        alertMessage += 'Ngày hết hạn thẻ Visa không hợp lệ (định dạng MM/YY).\n';
                        visaValid = false;
                        displayFieldError(cardExpiryInput, 'Ngày hết hạn không hợp lệ (MM/YY).');
                    }
                    if (!cardCvc || !/^\d{3,4}$/.test(cardCvc)) {
                        alertMessage += 'Mã CVC/CVV thẻ Visa không hợp lệ (3 hoặc 4 chữ số).\n';
                        visaValid = false;
                        displayFieldError(cardCvcInput, 'Mã CVC/CVV không hợp lệ (3-4 chữ số).');
                    }
                    if (!cardHolderName) {
                        alertMessage += 'Tên chủ thẻ không được để trống.\n';
                        visaValid = false;
                        displayFieldError(cardHolderNameInput, 'Tên chủ thẻ không được để trống.');
                    }

                    if (!visaValid) {
                        const globalErrorContainer = document.querySelector('.payment-global-error-js');
                        if (globalErrorContainer) {
                            globalErrorContainer.textContent = 'Vui lòng kiểm tra lại các thông tin thẻ Visa đã nhập.';
                            globalErrorContainer.style.display = 'block';
                        }
                        event.preventDefault(); 
                    } else {
                        const globalErrorContainer = document.querySelector('.payment-global-error-js');
                        if (globalErrorContainer) globalErrorContainer.style.display = 'none';
                    }
                }
            });
        }
    }

    // Hàm phụ trợ để hiển thị lỗi ngay dưới input field
    function displayFieldError(inputElement, message) {
        if (!inputElement) return;
        // Xóa lỗi cũ nếu có
        const oldError = inputElement.parentElement.querySelector('.payment-form-error-text');
        if (oldError) oldError.remove();

        const errorSpan = document.createElement('span');
        errorSpan.className = 'payment-form-error-text error-text-booking'; 
        errorSpan.textContent = message;
        inputElement.parentElement.appendChild(errorSpan); 
    }

});
