@echo off
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0\checkout.php
php "%BIN_TARGET%" %*