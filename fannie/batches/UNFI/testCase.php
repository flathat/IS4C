<?php

            /* 
             * Case statement
             * 12x35ml
             * - $case_size
             * - $size
             * - $unitofmeasure
             * ?Put the raw $case somewhere in case the parsing doesn't work?
             */
$packageBit = '500ml';
$case = '12/case';
//$case = "Each";
//$case = "12x35.71ml";
//$case = "12x35ml";
echo "case:>{$case}<\n";
             $case_size = 0;
             $size = 0;
             $unitofmeasure = '';
             if ($case == '') {
                 // problem
                 $noop = 1;
             } else {
                 if (strtolower($case) == 'each') {
                     $case_size = 1;
                     $size = 1;
                     $unitofmeasure = 'ct'; // 'ea'?
                 } elseif (strpos($case,'x')>0) {
                     if (preg_match("/(\d+)x(.+)$/",$case,$matches)) {
                         $case_size = $matches[1];
                         $package = $matches[2];
                         if (preg_match("/(\d+\.*\d*)(\D+)$/",$package,$matches)) {
                             $size = $matches[1];
                             $unitofmeasure = $matches[2];
                         }
                     } else {
                         // problem
                         $noop = 1;
                     }
                 } elseif (strpos($case,'/')>0) {
                     if (preg_match("/(\d+)\/(.+)$/",$case,$matches)) {
                         $case_size = $matches[1];
                         $package = $matches[2];
                         if (strtolower($package) == 'case') {
                             if ($packageBit != '') {
                                 $package = trim($packageBit,'()');
                             } else {
                                 //Problem?
                                 $noop = 1;
                             }
                         }
                         if (preg_match("/(\d+\.*\d*)(\D+)$/",$package,$matches)) {
                             $size = $matches[1];
                             $unitofmeasure = $matches[2];
                         }
                     } else {
                         // problem
                         $noop = 1;
                     }
                 } else {
                     $noop=1;
                 }
             }

echo "case_size:>{$case_size}<\n";
echo "package:>{$package}<\n";
echo "size:>{$size}<\n";
echo "unitofmeasure:>{$unitofmeasure}<\n";
?>
