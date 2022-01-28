<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

class ImportCsvCustomersCommand extends Command
{
    /**
     * Метка не определенной локации
     */
    const UNKNOWN_LOCATION = 'Unknown';

    /**
     * Сигнатура команды
     *
     * @var string
     */
    protected $signature = 'customers:import-csv {file}';

    /**
     * Описание команды
     *
     * @var string
     */
    protected $description = 'Импорт клиентов из csv файла';

    /**
     * @var null|array
     */
    private $iso = null;

    /**
     * @return void
     */
    public function handle()
    {
        $file = $this->argument('file');

        // Проверяем существование файла
        if (!is_file($file)) {
            // На случай если файл положили рядом с командой
            $file = __DIR__ . DIRECTORY_SEPARATOR . $file;
            if (!is_file($file)) {
                $this->error(sprintf('Файла %s не существует', $file));
                return;
            }
        }

        // Проверяем возможность прочитать файл
        if (!is_writable($file)) {
            $this->error(sprintf('Файл %s не доступен к чтению', $file));
            return;
        }

        $success = $errors = [];

        // Обрабатываем файл
        foreach ($this->rows(fopen($file, 'r')) as $row) {
            $this->process($row, $success, $errors);
        }
        // Если есть успешные записи, то их сохраняем
        if (!empty($success)) {
            DB::table('customers')->insert($success);
        }
        // Если есть ошибочные записи, то генерируем отчет
        if (!empty($errors)) {
            $this->generateErrorReport($errors);
        }

        $this->info('Импорт завершен!');
        $this->info(sprintf('Импортировано успешно строк:%d', count($success)));
        $this->warn(sprintf('Обработано строк c ошибками:%d', count($errors)));
    }

    private function process($row, &$data, &$errors): void
    {
        $result = [];
        [$rowId, $rowName, $rowEmail, $rowAge, $rowLocation] = $row;
        $nameExplode = explode(' ', (string)$rowName);
        $result['name'] = (string)$nameExplode[0];
        $result['surname'] = (string)$nameExplode[1];
        $result['email'] = (string)$rowEmail;
        $result['age'] = (int)$rowAge;
        $result['location'] = (string)$rowLocation ?: self::UNKNOWN_LOCATION;
        $result['country_code'] = $this->getCountryIsoCode($result['location']);

        // Валидация
        $validator = Validator::make($result, [
            'name' => 'required|max:255',
            'surname' => 'required|max:255',
            'email' => 'required|max:255|email:rfc,dns',
            'age' => 'integer|between:18,99',
            'location' => 'required|max:255',
            'country_code' => 'max:3|nullable',
        ]);

        if ($validator->fails()) {
            $errors[] = [
                'row' => $row,
                'errors' => $validator->errors(),
            ];
            return;
        }


        $data[] = $result;
    }

    /**
     * Получение ISO3 кода локации
     *
     * @param $location
     *
     * @return string|null
     */
    private function getCountryIsoCode($location): ?string
    {
        if (is_null($this->iso)) {
            $codes = json_decode(file_get_contents('http://country.io/iso3.json'), true);
            $names = json_decode(file_get_contents('http://country.io/names.json'), true);
            $this->iso = [];
            foreach ($codes as $iso2 => $iso3) {
                if (isset($names[$iso2])) {
                    $this->iso[strtoupper($names[$iso2])] = $iso3;
                }
            }
        }

        return $this->iso[strtoupper($location)] ?? null;
    }

    /**
     * Используем генератор на случай получения большого файла, для оптимальной работы с памятью
     *
     * @param $stream
     *
     * @return \Generator|void
     */
    private function rows($stream)
    {
        // Пропускаем первую строку файла с заголовками
        fgets($stream);
        while (!feof($stream)) {
            $row = fgetcsv($stream);

            yield $row;
        }

        return;
    }

    /**
     * Генерируем отчет в формате csv, так как он спокойно откроется в Excel
     *
     * @param array $errors
     *
     * @return void
     */
    private function generateErrorReport(array $errors): void
    {
        $report = __DIR__ . DIRECTORY_SEPARATOR . sprintf('import-error-report-%s.csv', date('Ymd-His'));

        $fp = fopen($report, 'w');
        fputcsv($fp, ['id', 'name', 'email', 'age', 'location', 'error']);
        foreach ($errors as $error) {
            /** @var MessageBag $rowError */
            $errorBag = $error['errors'];
            $keys = $errorBag->keys();
            $row = $error['row'];
            foreach ($keys as $key) {
                $row[] = $key;
                fputcsv($fp, $row);
            }
        }
        fclose($fp);
    }
}
