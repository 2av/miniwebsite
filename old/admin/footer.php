<?php
// Simple admin footer
?>

<script>
// FAQ Management JavaScript
function editFaq(faq) {
    document.getElementById('edit_id').value = faq.id;
    document.getElementById('edit_page_type').value = faq.page_type;
    document.getElementById('edit_question').value = faq.question;
    document.getElementById('edit_answer').value = faq.answer;
    document.getElementById('edit_sort_order').value = faq.sort_order;
    document.getElementById('edit_status').value = faq.status;
    $('#editFaqModal').modal('show');
}

function deleteFaq(id) {
    if (confirm('Are you sure you want to delete this FAQ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

</body>
</html>
