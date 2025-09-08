<?php
session_start();
session_unset();
session_destroy();
header("Location: index.php?msg=" . urlencode("Sei stato disconnesso."));
exit();
