<form enctype="multipart/form-data" action="upload.php" method="POST">
    <!-- Поле MAX_FILE_SIZE должно быть указано до поля загрузки файла -->
    <input type="hidden" name="MAX_FILE_SIZE" value="15000000" />
    <!-- Название элемента input определяет имя в массиве $_FILES -->
    Отправить этот файл: <input name="userfile" type="file" /><br>
	Комментарий: <input name="comment" type="text" /><br>
    <input type="submit" value="Send File" />
</form>