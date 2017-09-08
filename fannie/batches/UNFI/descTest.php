<?php
            /* Description
             * Sometimes has:
             * - "Organic", "GF" at start that are better as flags.
             * - NEW at the end
             * - package and case info
             * - long further description in parens that we cannot use.
             * - short bits in parens that we can maybe use
             */
$description = "";
$description = "Organic Thermo-Protector (one two three) Hair Care (Leave-In) (Very Dry Skin) 35g  NEW";
$description = "Pineapple Enzyme Facial Cleanser   (Gently loosen, dissolve & wash away impurities)";
$description = "Coco 500ml Gluten Free Water";

//$description = "Mango Water (500ml)";
echo "desc>{$description}<\n";
/* preg_match syntax
$caseStrings = "cases only|by the case";
$description = "sold BY THE CASE";
$description = "sold BY cases ONly";
echo "desc>{$description}<\n";
//if (preg_match('/(cases only|by the case)/i', $description)) {
if (preg_match("/({$caseStrings})/i", $description)) {
    echo "Found case\n";
    exit;
} else {
    echo "Not found case\n";
    exit;
}
 */
            $description = preg_replace("/ +NEW$/","",$description);
            $description = preg_replace("/Gluten Free/i","Gluten-Free",$description);
            $dParts = preg_split("/ +/",$description);
            $dPartsLC = preg_split("/ +/",strtolower($description));
            $dParens = array();
            $organic = "";
            $gf = "";
            $nonGMO = "";
            $useDesc = "";
            $dpCount = count($dParts);
            $dpp = -1;
            $inParen = False;
            $packageBit = "";
            //foreach ($dParts as $dpart) { }
            for ($dp = 0 ; $dp < $dpCount ; $dp++) {
                // Whole token is in parens.
                if (preg_match("/\([^)]+\)/",$dParts[$dp])) {
                    $dNoParens = trim($dParts[$dp],'()');
                    if (preg_match("/\d\D+$/",$dNoParens)) {
                        $packageBit = $dNoParens;
                        continue;
                    }
                    $dpp++;
                    $dParens[$dpp] = $dParts[$dp];
                    continue;
                }
                // End of paren'ed token.
                if (substr($dParts[$dp],-1,1) == ')' && $inParen) {
                    $dParens[$dpp] .= (' ' . $dParts[$dp]);
                    $inParen = False;
                    continue;
                }
                // Mid-paren token.
                if ($inParen) {
                    $dParens[$dpp] .= (' ' . $dParts[$dp]);
                    continue;
                }
                // Start paren'ed token.
                if (substr($dParts[$dp],0,1) == '(') {
                    $dpp++;
                    $dParens[$dpp] = $dParts[$dp];
                    $inParen = True;
                    continue;
                }
                // Flag-words
                if ($dPartsLC[$dp] == "organic") {
                    $organic = $dParts[$dp];
                    continue;
                }
                if (
                    $dPartsLC[$dp] == "gf" ||
                    $dPartsLC[$dp] == "g/f" ||
                    $dPartsLC[$dp] == "gluten-free"
                ) {
                    $gf = $dParts[$dp];
                    continue;
                }
                // Package info at the end
                if (true || $dp == ($dpCount - 1)) {
                    //if (preg_match("/\d(ml|g|L|kg)/",$dParts[$dp])) {}
                    if (preg_match("/^\d+\D+$/",$dParts[$dp])) {
                        $packageBit = $dParts[$dp];
                        continue;
                    }
                }

                // Keep the token
                $useDesc .= ($useDesc == '') ? '' : ' ';
                $useDesc .= $dParts[$dp];
                
            }

            /* Append organic and GF "flags" so they will be available in
             * imports from vendorItems.
             */
            if ($organic != '' && $gf != '') {
                $useDesc = substr($useDesc,0,45) . ' O GF';
            } elseif ($organic != '') {
                $useDesc = substr($useDesc,0,48) . ' O';
            } elseif ($gf != '') {
                $useDesc = substr($useDesc,0,47) . ' GF';
            } else {
                $noop = 1;
            }

            echo "useDesc>{$useDesc}<\n";
            echo "packageBit>{$packageBit}<\n";
            echo "organic>{$organic}<\n";
            echo "gf>{$gf}<\n";
            foreach ($dParens as $paren) {
                echo "dP>{$paren}<\n";
            }

?>
