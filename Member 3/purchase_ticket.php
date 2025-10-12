<?php
$tickets = getTicketsByEvent($_GET['event_id']);
?>

<form method="POST" action="purchase.php">
    <input type="hidden" name="event_id" value="<?php echo $_GET['event_id']; ?>">
    <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">

    <label for="ticket_id">Ticket Type:</label>
    <select name="ticket_id" id="ticket_id" required>
        <?php foreach ($tickets as $ticket): ?>
            <option value="<?php echo $ticket['id']; ?>"><?php echo $ticket['ticket_type']; ?> - $<?php echo $ticket['price']; ?></option>
        <?php endforeach; ?>
    </select>

    <label for="quantity">Quantity:</label>
    <input type="number" id="quantity" name="quantity" min="1" required>

    <button type="submit">Purchase</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');

    form.addEventListener('submit', function(event) {
        const quantity = document.getElementById('quantity').value;

        if (!quantity || isNaN(quantity) || quantity <= 0) {
            alert('Quantity must be a positive number.');
            event.preventDefault();
        }
    });
});
</script>