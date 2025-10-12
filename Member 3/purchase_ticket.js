document.addEventListener('DOMContentLoaded', function() {
    const ticketSelect = document.getElementById('ticketId');
    fetch('/tickets?event_id=1') // Assuming event_id is 1
        .then(response => response.json())
        .then(tickets => {
            tickets.forEach(ticket => {
                const option = document.createElement('option');
                option.value = ticket.id;
                option.text = `${ticket.ticket_type} - $${ticket.price}`;
                ticketSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error:', error));
});
document.getElementById('purchaseForm').addEventListener('submit', function(event) {
    event.preventDefault();
    alert('Form submitted'); // Debug: Confirm form submission
    this.submit();
});