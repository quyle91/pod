#!/bin/bash

# clone source từ folder gốc
SOURCE_FOLDER="/mnt/c/Users/Administrator/Documents/flatsome-child"

# Lưu tên file script để tránh bị xóa
SCRIPT_NAME="$(basename "$0")"

# Lấy thư mục hiện tại
CURRENT_DIR="$(pwd)"

# Xóa toàn bộ thư mục con trừ file script
for dir in */; do
    if [ "$dir" != "" ]; then
        rm -rf "$dir"
    fi
done

# Xóa toàn bộ file trừ file script
for file in *; do
    if [ "$file" != "$SCRIPT_NAME" ]; then
        rm -f "$file"
    fi
done

# Copy toàn bộ file từ SOURCE_FOLDER vào thư mục hiện tại
cp -r "$SOURCE_FOLDER"/. "$CURRENT_DIR"

# echo "Đã xoá và copy toàn bộ file từ $SOURCE_FOLDER vào $CURRENT_DIR, trừ $SCRIPT_NAME"