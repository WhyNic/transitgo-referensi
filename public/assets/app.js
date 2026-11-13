'use strict';

for (const form of document.querySelectorAll('[data-confirm]')) {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
}

const seatForm = document.querySelector('[data-seat-form]');
if (seatForm) {
    const seats = [...seatForm.querySelectorAll('input[type="checkbox"]:not(:disabled)')];
    const limit = Number(seatForm.dataset.seatLimit);
    const button = seatForm.querySelector('button');
    const countLabel = seatForm.querySelector('[data-seat-count]');

    const updateSeats = () => {
        const count = seats.filter((seat) => seat.checked).length;
        for (const seat of seats) {
            seat.disabled = count >= limit && !seat.checked;
        }
        button.disabled = count !== limit;
        countLabel.textContent = count + ' / ' + limit + ' dipilih';
    };

    seatForm.addEventListener('change', updateSeats);
    window.addEventListener('pageshow', updateSeats);
    updateSeats();
}
