<?php
// Redirect ke beranda jika tidak ada parameter
if (!isset($_GET['page'])) {
    header('Location: beranda.html');
    exit;
}
?>
