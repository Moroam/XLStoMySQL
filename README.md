# XLStoMySQL
Tool for importing files to MySQL

Basic possibilities:
1. Working with a variety of table formats (for reading files, use the class ReadXLS based on PhpSpreadsheet)
2. Setting parameters for import tasks that can be reused
3. Storing import task parameters in the database
3. Working with LARGE tables file (files with hundreds of thousands of rows and dozens of columns, it only depends on the amount of allocated memory for PHP)
4. Checking data from the file to match the data template (class DataTest)
5. Running MySQL procedures after performing the import.

See the usage example

*Comment: Tables in ODS and XLSX format (XLS, CSV etc) have different row numbering. In ODS tables row numbering starts with 1, in XLSX it starts with 0.*

# XLStoMySQL
Инструмент для импорта файлов в MySQL

Основные возможности:
1. Работа с разнообразными форматами таблиц (для чтения файлов используется class ReadXLS основанный на PhpSpreadsheet)
2. Задание параметров задач импорта с возможностью их повторного использования
3. Хранение параметров задач импорта в базе данных
3. Работа с БОЛЬШИМИ таблицами (файлы с сотнями тысяч строк и десятками столбцов, упирается лишь в объем выделенной памяти для PHP)
4. Проверка данных из файла на соответствие шаблону данных (class DataTest)
5. Запуск процедур MySQL, после выполнения импорта.

Смотри пример использования

*Замечание: В таблицах в формате ODS и XLSX (XLS, CSV etc) отличается нумерация строк. Для таблиц ODS нумерация строк начинается с 1, в XLSX с 0.*
