<?php
require __DIR__ . '/lib.php';
session_destroy();
header('Location: login.php');
