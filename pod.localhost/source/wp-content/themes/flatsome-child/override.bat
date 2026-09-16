@echo off
@REM Ghi đè source vào folder gốc
set SOURCE_FOLDER=D:\Laragon\www\flatsome\wp-content\themes\flatsome-child

:: Lưu tên file script để tránh bị xóa
set SCRIPT_NAME=%~nx0

:: Kiểm tra xem SOURCE_FOLDER có tồn tại không
if not exist "%SOURCE_FOLDER%" (
    echo Thư mục nguồn không tồn tại: %SOURCE_FOLDER%
    pause
    exit /b
)

:: Xóa toàn bộ nội dung trong SOURCE_FOLDER trừ file .bat này
for /d %%D in ("%SOURCE_FOLDER%\*") do (
    if /I not "%%D"=="%SOURCE_FOLDER%\%SCRIPT_NAME%" rd /s /q "%%D"
)
for %%F in ("%SOURCE_FOLDER%\*") do (
    if /I not "%%F"=="%SOURCE_FOLDER%\%SCRIPT_NAME%" del /f /q "%%F"
)

:: Copy toàn bộ file từ thư mục hiện tại vào SOURCE_FOLDER
xcopy /E /I /Y "%CD%" "%SOURCE_FOLDER%" /EXCLUDE:%SCRIPT_NAME%

:: Thông báo hoàn tất
@REM echo Đã xoá toàn bộ nội dung trong %SOURCE_FOLDER% và sao chép từ %CD%.
