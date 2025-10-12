<form method="POST" action="tickets.php">
    <label for="event_id">Event ID:</label>
    <input type="number" id="event_id" name="event_id" required>
    
    <label for="category_id">Category ID:</label>
    <input type="number" id="category_id" name="category_id" required>
    
    <label for="ticket_type">Ticket Type:</label>
    <input type="text" id="ticket_type" name="ticket_type" required>
    
    <label for="price">Price:</label>
    <input type="number" id="price" name="price" step="0.01" required>
    
    <label for="quantity">Quantity:</label>
    <input type="number" id="quantity" name="quantity" required>
    
    <button type="submit">Add Ticket</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');

    form.addEventListener('submit', function(event) {
        const event_id = document.getElementById('event_id').value;
        const category_id = document.getElementById('category_id').value;
        const ticket_type = document.getElementById('ticket_type').value;
        const price = document.getElementById('price').value;
        const quantity = document.getElementById('quantity').value;

        if (!event_id || !category_id || !ticket_type || !price || !quantity) {
            alert('All fields are required.');
            event.preventDefault();
        } else if (isNaN(event_id) || isNaN(category_id) || isNaN(quantity)) {
            alert('Event ID, Category ID, and Quantity must be numbers.');
            event.preventDefault();
        } else if (price <= 0 || quantity <= 0) {
            alert('Price and Quantity must be greater than zero.');
            event.preventDefault();
        }
    });
});
</script>