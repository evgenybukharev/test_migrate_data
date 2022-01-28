В данном тестовом задании была создана команда ImportCsvCustomersCommand.php для загрузки данных из csv файла.
Данная команда представляет из себя файл команды фреймворка Laravel/Lumen

Для запуска команды необходимо: 
1. переместить файл ImportCsvCustomersCommand.php в app/Console/Commands
2. В файле app/Console/Kernel.php зарегистрировать команду в свойстве класса $commands
3. Положить файл random.csv рядом с командой, или использовать путь к файлу при вызове команды
4. Из консоли вызвать команду php artisan customers:import-csv random.csv

Дополнительные комментарии:
1. В описании задания было указано что поле нужно привести к виду age - TIMESTAMP, при этом сказано что допустимое значени для age это 18 - 99, TIMESTAMP не предназначен для хранения целочисленых данных
2. В команде для чтения файла используется генератор, чтобы более оптимально обрабатывать файлы большого размера
3. ISO3 код страны берется из http://country.io, есть другие варианты получения данного кода, как пример использование стороних библиотек.
4. Для валидации данных используется валидатор Laravel, есть другие варианты реализации валидации, но в данном случае такой подход оправдан
5. Отчет об ошибках генеририруется в csv формат, данный формат можно открыть в Excel, как вариант отчет можно генерировать в xlsx формат, с использованием дополнительных библиотек.

Спасибо за внимание ;)