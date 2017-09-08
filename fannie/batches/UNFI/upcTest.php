<?php

    /*
     * Return the integer checkdigit for a UPC code.
     * Works for: UPC-A (10 or 11 +1), EAN13 (12+1) and
     *  a 13+1 format I don't know the name for.
     *  Works means: agrees with Neal Brothers checkdigits
     *   and a few other tests.
     * Based on: http://www.codediesel.com/php/generating-upc-check-digit/
     */
    function generate_upc_checkdigit($upc_code)
    {

        $odd_total  = 0;
        $even_total = 0;
        $check_digit = 0;
        if (strlen($upc_code) < 11) {
            $upc_code = str_pad($upc_code, 11, '0', STR_PAD_LEFT);
        }
        //$upc_chars = explode('',$upc_code);
        $upc_chars = str_split($upc_code);
        $chars_max = count($upc_chars);

        for($i=0; $i<$chars_max; $i++)
        {
            echo "\n$i. {$upc_chars[$i]}";
            if((($i+1)%2) == 0) {
                /* Sum even digits */
                $even_total += $upc_chars[$i];
                //$even_total += $upc_code[$i];
            } else {
                /* Sum odd digits */
                $odd_total += $upc_chars[$i];
                //$odd_total += $upc_code[$i];
            }
        }

        $sum = (3 * $odd_total) + $even_total;

        /* Get the remainder MOD 10*/
        $check_digit = $sum % 10;

        /* If the result is not zero, subtract the result from ten. */
        $check_digit = ($check_digit > 0) ? 10 - $check_digit : $check_digit;

        return $check_digit;
    }

    $raw = '87236500001 4'; // 11-digit
    $raw = '87000100007 7'; // 11-digit
    //$raw = '03120044618 3';
    $raw = '8411411780 9'; // 10-digit
    $raw = '087000100007 7'; // 11-digit -> EAN. Fails.
    $raw = '520011117330 2'; // EAN, OK.
    $raw = '520011746035 2'; // EAN, OK.
    $raw = '520011117330 2'; // EAN, OK.
    $raw = '520011746037 6'; // EAN, fails. s/b 0?
    $raw = '520011746038 3'; // EAN, fails. s/b 9?
    $raw = '008411411780 9'; // 10-digit -> EAN Fails
    $raw = '1062907050029 5'; // Strangely OK, prefix "10"
    $raw = '62907050019 9'; // As in NB. OK
    $raw = '1062907050019 9'; // As in NB -> EAN?. Fails, calculates 6.
    $raw = ''; // 
    $raw = '1062845186801 6'; // 13+1 = What? As in NB. OK. Chickpea Pasta.
    $raw = '801643800139 9 '; //12+ 1 = EAN? As in NB, OK. Earth's Choice Lemon Juice
    $raw = '1081141100002 5'; // 13+1 As in NB, OK. Edgy Veggie.
    $raw = '5574228448 5'; // OK
    $raw = '06780000248 1'; // OK
    $raw = '85794100187 6'; // OK

    list($upc,$checkdigit) = explode(' ',$raw,2);
    echo "Raw: upc: $upc checkdigit: $checkdigit";
    $calcCheck = generate_upc_checkdigit($upc);
    echo " Calc: $calcCheck\n";
    if ($checkdigit == $calcCheck) {
        echo "Agree: checkdigit: $checkdigit Calc: $calcCheck\n";
    } else {
        echo "Disagree: checkdigit: $checkdigit Calc: $calcCheck\n";
    }


?>
