<?php

$vars = [
    'B01003_001' => 'population',
    'B25046_001' => 'vehicles', //missing in some areas
    'B08014_003' => 'vehicles_1', //only counts workers. Perhaps consider B08201_003 which counts households
    'B08014_004' => 'vehicles_2',
];

$boroughs = ['061' => 1, '005' => 2, '047' => 3, '081' => 4, '085' => 5];

$a = json_decode(
        file_get_contents('https://api.census.gov/data/2020/acs/acs5?get='
        . implode(',', array_map(function($s) {return $s.'E,'.$s.'M';}, array_keys($vars)))
        . '&for=tract:*&in=state:36+county:061,047,085,081,005')
    );

$header = $a[0];
unset($a[0]);

foreach ($header as &$field) {
    foreach ($vars as $k => $v) {
        if ($k . 'E' == $field) {
            $field = $v;
            break;
        } elseif ($k . 'M' == $field) {
            $field = $v . '_error';
            break;
        }
    }
}

$f = fopen('import/census/vehicle_ownership.csv', 'w');

fprintf($f, "%s\n", implode(',', $header));

foreach ($a as $row) {
    fputcsv($f, $row);
}

fclose($f);
