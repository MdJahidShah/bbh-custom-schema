document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('bbhcuschma-schema-toggle');
    var box = document.getElementById('bbhcuschma-schema-box');
    toggle.addEventListener('click', function() {
        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
            toggle.textContent = '▼ Custom Schema (Click to Collapse)';
        } else {
            box.style.display = 'none';
            toggle.textContent = '➤ Custom Schema (Click to Expand)';
        }
    });
});
