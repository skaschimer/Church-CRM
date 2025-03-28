<?php

require_once '../Include/Config.php';
require_once '../Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Reports\PdfLabel;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;

function GroupBySalutation(string $famID, $aAdultRole, $aChildRole)
{
    // Function to place the name(s) on a label when grouping multiple
    // family members on the same label.
    // Make it put the name if there is only one adult in the family.
    // Make it put two first names and the last name when there are exactly
    // two adults in the family (e.g. "Nathaniel & Jeanette Brooks")
    // Make it put two whole names where there are exactly two adults with
    // different names (e.g. "Doug Philbrook & Karen Andrews")
    // When there are zero adults or more than two adults in the family just
    // use the family name.  This is helpful for sending newsletters to places
    // such as "All Souls Church"
    // Similar logic is applied if mailing to Sunday School children.

    $sSQL = 'SELECT * FROM family_fam WHERE fam_ID=' . $famID;
    $rsFamInfo = RunQuery($sSQL);

    if (mysqli_num_rows($rsFamInfo) === 0) {
        return 'Invalid Family' . $famID;
    }

    $aFam = mysqli_fetch_array($rsFamInfo);
    extract($aFam);

    // Only get family members that are in the cart
    $sSQL = 'SELECT * FROM person_per WHERE per_fam_ID=' . $famID . ' AND per_ID IN ('
    . convertCartToString($_SESSION['aPeopleCart']) . ') ORDER BY per_LastName, per_FirstName';

    $rsMembers = RunQuery($sSQL);
    $numMembers = mysqli_num_rows($rsMembers);

    // Initialize to "Nothing to return"  If this value is returned
    // the calling program knows to skip this mode and try the next

    $sNameAdult = 'Nothing to return';
    $sNameChild = 'Nothing to return';
    $sNameOther = 'Nothing to return';

    $numAdult = 0;
    $numChild = 0;
    $numOther = 0;

    for ($ind = 0; $ind < $numMembers; $ind++) {
        $member = mysqli_fetch_array($rsMembers);
        extract($member);

        $bAdult = false;
        $bChild = false;

        // Check if this person is adult
        foreach ($aAdultRole as $value) {
            if ($per_fmr_ID == $value) {
                $aAdult[$numAdult++] = $member;
                $bAdult = true;
            }
        }

        // Now check if this person is a child.  Note, if child and adult directory roles
        // overlap the person will only be listed once as an adult (can't be adult and
        // child simultaneously ... even if directory roles suggest otherwise)
        if (!$bAdult) {
            foreach ($aChildRole as $value) {
                if ($per_fmr_ID == $value) {
                    $aChild[$numChild++] = $member;
                    $bChild = true;
                }
            }
        }

        // If this is not an adult or a child it must be something else.  Maybe it's
        // another church or the landscape company that mows the lawn.
        if (!$bAdult && !$bChild) {
            $aOther[$numOther++] = $member;
        }
    }

    // Generate Salutation for Adults in family
    if ($numAdult === 1) {
        extract($aAdult[0]);
        $sNameAdult = $per_FirstName . ' ' . $per_LastName;
    } elseif ($numAdult == 2) {
        $firstMember = mysqli_fetch_array($rsMembers);
        extract($aAdult[0]);
        $firstFirstName = $per_FirstName;
        $firstLastName = $per_LastName;
        $secondMember = mysqli_fetch_array($rsMembers);
        extract($aAdult[1]);
        $secondFirstName = $per_FirstName;
        $secondLastName = $per_LastName;
        if ($firstLastName == $secondLastName) {
            $sNameAdult = $firstFirstName . ' & ' . $secondFirstName . ' ' . $firstLastName;
        } else {
            $sNameAdult = $firstFirstName . ' ' . $firstLastName . ' & ' .
                            $secondFirstName . ' ' . $secondLastName;
        }
    } elseif ($numAdult > 2) {
        $sNameAdult = $fam_Name;
    }

    // Assume all last names are the same
    $bSameLastNames = true;

    // Salutation for children grouped together
    if ($numChild > 0) {
        $firstMember = mysqli_fetch_array($rsMembers);
        extract($aChild[0]);
        $firstFirstName = $per_FirstName;
        $firstLastName = $per_LastName;
    }
    if ($numChild > 1) {
        $secondMember = mysqli_fetch_array($rsMembers);
        extract($aChild[1]);
        $secondFirstName = $per_FirstName;
        $secondLastName = $per_LastName;
        $bSameLastNames = $bSameLastNames && ($firstLastName == $secondLastName);
    }
    if ($numChild > 2) {
        $thirdMember = mysqli_fetch_array($rsMembers);
        extract($aChild[2]);
        $thirdFirstName = $per_FirstName;
        $thirdLastName = $per_LastName;
        $bSameLastNames = $bSameLastNames && ($secondLastName == $thirdLastName);
    }
    if ($numChild > 3) {
        $fourthMember = mysqli_fetch_array($rsMembers);
        extract($aChild[3]);
        $fourthFirstName = $per_FirstName;
        $fourthLastName = $per_LastName;
        $bSameLastNames = $bSameLastNames && ($thirdLastName == $fourthLastName);
    }
    if ($numChild == 1) {
        $sNameChild = $per_FirstName . ' ' . $per_LastName;
    }
    if ($numChild == 2) {
        if ($bSameLastNames) {
            $sNameChild = $firstFirstName . ' & ' . $secondFirstName . ' ' . $firstLastName;
        } else {
            $sNameChild = $firstFirstName . ' ' . $firstLastName . ' & ' .
                            $secondFirstName . ' ' . $secondLastName;
        }
    }
    if ($numChild == 3) {
        if ($bSameLastNames) {
            $sNameChild = $firstFirstName . ', ' . $secondFirstName . ' & ' .
                                            $thirdFirstName . ' ' . $firstLastName;
        } else {
            $sNameChild = $firstFirstName . ', ' . $secondFirstName . ' & ' .
                                            $thirdFirstName . ' ' . $fam_Name;
        }
    }
    if ($numChild == 4) {
        if ($bSameLastNames) {
            $sNameChild = $firstFirstName . ', ' . $secondFirstName . ', ' .
                        $thirdFirstName . ' & ' . $fourthFirstName . ' ' . $firstLastName;
        } else {
            $sNameChild = $firstFirstName . ', ' . $secondFirstName . ', ' .
                        $thirdFirstName . ' & ' . $fourthFirstName . ' ' . $fam_Name;
        }
    }
    if ($numChild > 4) {
        $sNameChild = 'The ' . $fam_Name . ' Family';
    }

    if ($numOther) {
        $sNameOther = $fam_Name;
    }

    unset($aName);

    $aName['adult'] = mb_substr($sNameAdult, 0, 33);
    $aName['child'] = mb_substr($sNameChild, 0, 33);
    $aName['other'] = mb_substr($sNameOther, 0, 33);

    return $aName;
}

function MakeADCArray(string $sADClist): array
{
    $aReturnArray = [];

    // The end of each row is marked with the pipe | symbol
    // keep fetching rows until gone
    while (mb_substr_count($sADClist, '|')) {
        // Find end of current row
        $endOfRow = strpos($sADClist, '|');
        if ($endOfRow) {
            $currentRow = mb_substr($sADClist, 0, $endOfRow);
            $sADClist = mb_substr($sADClist, $endOfRow + 1);

            // Find the current adc (hint, last item listed)
            $currentRow = trim($currentRow);
            $adc = mb_substr($currentRow, strrpos($currentRow, ' '));
            $adc = trim($adc, " ,\t\n\r\0\x0B");

            // Now get a list of the three digit codes associated
            // with this adc.  They are all before the "_" character

            $currentRow = mb_substr($currentRow, 0, strpos($currentRow, '_'));
            $currentRow = trim($currentRow, " ,\t\n\r\0\x0B");
            while (strlen($currentRow)) {
                if (strpos($currentRow, ',')) {
                    $nugget = trim(mb_substr($currentRow, 0, strpos($currentRow, ',')));
                    $currentRow = trim(mb_substr($currentRow, strpos($currentRow, ',') + 1));
                } else {
                    // Parsing last element
                    $nugget = trim($currentRow, " ,\t\n\r\0\x0B");
                    $currentRow = '';
                }

                $dash = strpos($nugget, '-');
                // Range of
                if ($dash) {
                    $start = intval(mb_substr($nugget, 0, $dash));
                    $end = intval(mb_substr($nugget, $dash + 1));
                    if ($end >= $start) {
                        for ($i = $start; $i <= $end; $i++) {
                            $aReturnArray[$i] = $adc;
                        }
                    }
                } else {
                    $i = intval($nugget);
                    $aReturnArray[$i] = $adc;
                }
            }
        }
    }

    return $aReturnArray;
}

function ZipBundleSort(array $inLabels)
{
    // Description:
    // sorts an input array $inLabels() for presort bundles
    //
    // Inputs:
    // $inLabels() is a 2-d associative array which must have:
    //  "Zip" as the location of the zipcode,
    //  the array is generally of the form
    //      $Labels[$i] = array('Name'=>$name, 'Address'=>$address,...'Zip'=>$zip)
    //
    //  Bundles will be returned in the following order:
    //  Bundles where full 5 digit zip count >= $iZip5MinBundleSize
    //  Bundles where 3 digit zip count >= $iZip3MinBundleSize
    //  Bundles where "ADC" count >= $iAdcMinBundleSize
    //      Mixed ADC bundle
    //
    // Return Values:
    // (1) The function returns an associative array which matches the input array containing any
    // legal bundles of "type" sorted by zip
    // (2) if no legal bundles are found for the requested "type" then the function returns "FALSE"
    // (3) the output array will also contain an associative value of "Notes" which will contain a
    //     text string to be printed on the labels indicating the bundle the label is a member of
    //
    // Notes:
    // (1) The ADC data is hard coded in the variable $adc was composed march 2006
    // (2) the definition of a "legal" bundle is one that contains at least $iMinBundleSize units
    // (3) this function is not PAVE certified
    //
    // Stephen Shaffer 2006, stephen@shaffers4christ.com
    //
    //////////////////////////////////////////////////////////////////////////////////////////////
    // 60
    // initialize the adc data list
    //
    // The following website is the source for the adc
    // http://pe.usps.com/text/dmm300/L004.htm
    // This array for STD mail
    // ADC array updated 2010-08-26

    $sADClist =
    '005, 115, 117-119                      _LONG ISLAND NY 117         |' .
    '006-009                                _ADC SAN JUAN PR 006        |' .
    '010-017                                _ADC SPRINGFIELD MA 010     |' .
    '018, 019, 021, 022, 024, 055           _ADC BOSTON MA 021          |' .
    '020, 023, 025-029                      _ADC PROVIDENCE RI 028      |' .
    '030-034, 038, 039                      _ADC PORTSMOUTH NH 038      |' .
    '035-037, 050-054, 056-059              _ADC WHITE RIV JCT VT 050   |' .
    '040-049                                _ADC PORTLAND ME 040        |' .
    '060-069                                _ADC SOUTHERN CT 064        |' .
    '070-079, 085-089                       _ADC DV DANIELS NJ 07099    |' .
    '080-084                                _ADC SOUTH JERSEY NJ 080    |' .
    '090-099                                _MILITARY CENTER NY 090     |' .
    '100-102, 104                           _ADC NEW YORK NY 100        |' .
    '103, 110-114, 116                      _ADC QUEENS NY 110          |' .
    '105-109                                _ADC WESTCHESTER NY 105     |' .
    '120-129                                _ADC ALBANY NY 120          |' .
    '130-139                                _ADC SYRACUSE NY 130        |' .
    '140-149                                _ADC BUFFALO NY 140         |' .
    '150-168, 260                           _ADC PITTSBURGH PA 150      |' .
    '169-178                                _ADC HARRISBURG PA 170      |' .
    '179, 189, 193-196                      _ADC SOUTHEASTERN PA 189    |' .
    '180-188                                _ADC LEHIGH VALLEY PA 180   |' .
    '190-192                                _ADC PHILADELPHIA PA 190    |' .
    '197-199                                _ADC WILMINGTON DE 197      |' .
    '200                                    _WASHINGTON DC 200          |' .
    '201, 220-223, 226, 227                 _ADC NORTHERN VA VA 220     |' .
    '202-205                                _ADC WASHINGTON DC 202      |' .
    '206-209                                _ADC SOUTHERN MD MD 207     |' .
    '210-212, 214-219, 254, 267             _ADC LINTHICUM MD 210       |' .
    '224, 225, 228-239, 244                 _ADC RICHMOND VA 230        |' .
    '240-243, 245                           _ADC ROANOKE VA 240         |' .
    '246-253, 255-259                       _ADC CHARLESTON WV 250      |' .
    '261-266, 268                           _ADC CLARKSBURG WV 263      |' .
    '270-279, 285                           _ADC GREENSBORO NC 270      |' .
    '280-284, 286-289, 297                  _ADC CHARLOTTE NC 280       |' .
    '290-296                                _ADC COLUMBIA SC 290        |' .
    '298, 300, 301, 305, 306, 308, 309      _ADC NORTH METRO GA 30197   |' .
    '299, 304, 313-315, 320-324, 326, 344   _ADC JACKSONVILLE FL 32088  |' .
    '302, 303, 311, 399                     _ADC ATLANTA GA 303         |' .
    '307, 370-374, 376-379, 384, 385        _ADC NASHVILLE TN 37099     |' .
    '310, 312, 316-319, 398                 _ADC MACON GA 31293         |' .
    '325, 365, 366, 394, 395                _ADC MOBILE AL 365          |' .
    '327-329, 334, 347, 349                 _ADC MID FLORIDA FL 32799   |' .
    '330-333, 340                           _ADC MIAMI FL 33298         |' .
    '335-339, 341, 342, 346                 _ADC TAMPA FL 335           |' .
    '350-352, 354-359, 362                  _ADC BIRMINGHAM AL 35099    |' .
    '360, 361, 363, 364, 367, 368           _ADC MONTGOMERY AL 36099    |' .
    '369, 390-393, 396, 397                 _ADC JACKSON MS 39099       |' .
    '375, 380-383, 386-389, 723             _ADC MEMPHIS TN 38099       |' .
    '400-409, 411-418, 420-427, 471, 476, 477
                                        _ADC LOUISVILLE KY 400      |' .
    '410, 450-455, 458, 459, 470            _ADC CINCINNATI OH 450      |' .
    '430-438, 456, 457                      _ADC COLUMBUS OH 430        |' .
    '439-449                                _ADC CLEVELAND OH 440       |' .
    '460-469, 472-475, 478, 479             _ADC INDIANAPOLIS IN 460    |' .
    '480-489, 492                           _ADC DETROIT MI 481         |' .
    '490, 491, 493-497                      _ADC GRAND RAPIDS MI 493    |' .
    '498, 499, 530-532, 534, 535, 537-539, 541-545, 549
                                        _ADC MILWAUKEE WI 530       |' .
    '500-509, 520-528, 612                  _ADC DES MOINES IA 50091    |' .
    '510-516, 680, 681, 683-693             _ADC OMAHA NE 680           |' .
    '540, 546-548, 550, 551, 556-559        _ADC ST PAUL MN 550         |' .
    '553-555, 560-564, 566                  _ADC MINNEAPOLIS MN 553     |' .
    '565, 567, 580-588                      _ADC FARGO ND 580           |' .
    '570-577                                _ADC SIOUX FALLS SD 570     |' .
    '590-599, 821                           _ADC BILLINGS MT 590        |' .
    '600-603, 610, 611, 614-616             _ADC CAROL STREAM IL 601    |' .
    '604, 605, 609, 613, 617-619            _ADC S SUBURBAN IL 604      |' .
    '606-608                                _ADC CHICAGO IL 606         |' .
    '620, 622-631, 633-639                  _ADC ST LOUIS MO 63203      |' .
    '640, 641, 644-658, 660-662, 664-668    _ADC KANSAS CITY MO 66340   |' .
    '669-679, 739                           _ADC WICHITA KS 67099       |' .
    '700, 701, 703, 704                     _ADC NEW ORLEANS LA 700     |' .
    '705-708                                _ADC BATON ROUGE LA 707     |' .
    '710-714                                _ADC SHREVEPORT LA 71099    |' .
    '716-722, 724-729                       _ADC LITTLE ROCK AR 72098   |' .
    '730, 731, 734-738, 748                 _ADC OKLAHOMA CITY OK 730   |' .
    '733, 779-789                           _ADC SAN ANTONIO TX 78099   |' .
    '740, 741, 743-747, 749                 _ADC TULSA OK 740           |' .
    '750-759                                _ADC NORTH TEXAS TX 750     |' .
    '760-769                                _ADC FT WORTH TX 760        |' .
    '770-778                                _ADC NORTH HOUSTON TX 773   |' .
    '790-797                                _ADC LUBBOCK TX 793         |' .
    '798, 799, 880, 885                     _ADC EL PASO TX 798         |' .
    '800-816                                _ADC DENVER CO 800          |' .
    '820, 822-831                           _ADC CHEYENNE WY 820        |' .
    '832-834, 836, 837, 979                 _ADC BOISE ID 836           |' .
    '835, 838, 980-985, 988-994, 998, 999   _ADC SEATTLE WA 980         |' .
    '840-847, 898                           _ADC SALT LAKE CTY UT 840   |' .
    '850-853, 855, 859, 860, 863            _ADC PHOENIX AZ 852         |' .
    '856, 857                               _ADC TUCSON AZ 856          |' .
    '864, 889-891, 893-895, 897, 961        _ADC LAS VEGAS NV 890       |' .
    '865, 870-875, 877-879, 881-884         _ADC ALBUQUERQUE NM 870     |' .
    '900-904                                _ADC LOS ANGELES CA 900     |' .
    '905-908                                _ADC LONG BEACH CA 907      |' .
    '910-912, 932, 933, 935                 _ADC PASADENA CA 910        |' .
    '913-916, 930, 931, 934                 _ADC SANTA CLARITA CA 913   |' .
    '917, 918                               _ADC INDUSTRY CA 917        |' .
    '919-921                                _ADC SAN DIEGO CA 920       |' .
    '922-925                                _ADC SN BERNARDINO CA 923   |' .
    '926-928                                _ADC SANTA ANA CA 926       |' .
    '936-939, 950, 951                      _ADC SAN JOSE CA 950        |' .
    '940, 941, 943, 944, 949, 954, 955      _ADC SAN FRANCISCO CA 940   |' .
    '942, 952, 953, 956-960                 _ADC SACRAMENTO CA 956      |' .
    '945-948                                _ADC OAKLAND CA 945         |' .
    '962-966                                _AMF SFO APO/FPO CA 962     |' .
    '967-969                                _ADC HONOLULU HI 967        |' .
    '970-978, 986                           _ADC PORTLAND OR 970        |' .
    '995-997                                _ADC ANCHORAGE AK 995       |';

    $adc = MakeADCArray($sADClist);

    if (SystemConfig::debugEnabled()) {
        foreach ($adc as $key => $value) {
            LoggerUtils::getAppLogger()->debug("key = $key, value = $value");
        }
    }

    // Step 1 - create an array of only the zipcodes of length 5
    $iZip5MinBundleSize = 15; // Minimum number of labels allowed in a 5 digit zip code bundle
    $iZip3MinBundleSize = 10; // Minimum number of labels allowed in a 3 digit zip code bundle
    $iAdcMinBundleSize = 10; // Minimum number of labels allowed in an ADC bundle

    $n = count($inLabels);
    $nTotalLabels = $n;

    for ($i = 0; $i < $n; $i++) {
        $Zips[$i] = intval(mb_substr($inLabels[$i]['Zip'], 0, 5));
    }

    $ZipCounts = array_count_values($Zips);

    $nz5 = 0;

    // Walk through the input array and pull all matching records where count >= $iZip5MinBundleSize
    foreach ($ZipCounts as $z => $zc) {
        if ($zc >= $iZip5MinBundleSize) {
            $NoteText = ['Note' => '******* Presort ZIP-5 ' . $z];
            $NameText = ['Name' => '** ' . $zc . ' Addresses in Bundle ' . $z . ' *'];
            $AddressText = ['Address' => '** ' . $nTotalLabels . ' Total Addresses *'];
            $CityText = ['City' => '******* Presort ZIP-5 ' . $z . '  '];
            $outList[] = array_merge($NoteText, $NameText, $AddressText, $CityText);
            for ($i = 0; $i < $n; $i++) {
                if (intval(mb_substr($inLabels[$i]['Zip'], 0, 5)) == $z) {
                    $outList[] = array_merge($inLabels[$i], $NoteText);
                    $inLabels[$i]['Zip'] = -1;
                    $nz5++;
                }
            }
        }
    }

    // Remove processed labels for inLabels array
    for ($i = 0; $i < $n; $i++) {
        if ($inLabels[$i]['Zip'] != -1) {
            $inLabels2[] = $inLabels[$i];
        }
    }
    unset($inLabels);
    $inLabels = $inLabels2;

    // Pass 2 looking for ZIP3 bundles
    unset($Zips);
    $n = count($inLabels);

    LoggerUtils::getAppLogger()->debug(print_r($inLabels));

    for ($i = 0; $i < $n; $i++) {
        $Zips[$i] = intval(mb_substr($inLabels[$i]['Zip'], 0, 3));
    }

    $ZipCounts = array_count_values($Zips);

    $nz3 = 0;

    // Walk through the input array and pull all matching records where count >= $iZip3MinBundleSize
    foreach ($ZipCounts as $z => $zc) {
        if ($zc >= $iZip3MinBundleSize) {
            $NoteText = ['Note' => '******* Presort ZIP-3 ' . $z];
            $NameText = ['Name' => '** ' . $zc . ' Addresses in Bundle ' . $z . ' *'];
            $AddressText = ['Address' => '** ' . $nTotalLabels . ' Total Addresses *'];
            $CityText = ['City' => '******* Presort ZIP-3 ' . $z . '  '];
            $outList[] = array_merge($NoteText, $NameText, $AddressText, $CityText);
            for ($i = 0; $i < $n; $i++) {
                if (intval(mb_substr($inLabels[$i]['Zip'], 0, 3)) == $z) {
                    $outList[] = array_merge($inLabels[$i], $NoteText);
                    $inLabels[$i]['Zip'] = -1;
                    $nz3++;
                }
            }
        }
    }

    unset($inLabels2);
    for ($i = 0; $i < $n; $i++) {
        if ($inLabels[$i]['Zip'] != -1) {
            $inLabels2[] = $inLabels[$i];
        }
    }
    unset($inLabels);
    $inLabels = $inLabels2;

    // Pass 3 looking for ADC bundles
    unset($Zips);
    $n = count($inLabels);

    for ($i = 0; $i < $n; $i++) {
        if (isset($adc[intval(mb_substr($inLabels[$i]['Zip'], 0, 3))])) {
            $Zips[$i] = $adc[intval(mb_substr($inLabels[$i]['Zip'], 0, 3))];
        }
    }

    unset($ZipCounts);
    if (isset($Zips)) {
        $ZipCounts = array_count_values($Zips);
    }

    $ncounts = 0;
    if (isset($ZipCounts)) {
        $ncounts = count($ZipCounts);
    }
    $nadc = 0;
    if ($ncounts) {
        // Walk through the input array and pull all matching records where count >= $iAdcMinBundleSize
        foreach ($ZipCounts as $z => $zc) {
            if ($zc >= $iAdcMinBundleSize) {
                $NoteText = ['Note' => '******* Presort ADC ' . $z];
                $NameText = ['Name' => '** ' . $zc . ' Addresses in Bundle ADC ' . $z . ' *'];
                $AddressText = ['Address' => '** ' . $nTotalLabels . ' Total Addresses *'];
                $CityText = ['City' => '******* Presort ADC ' . $z . '  '];
                $outList[] = array_merge($NoteText, $NameText, $AddressText, $CityText);
                for ($i = 0; $i < $n; $i++) {
                    if ($adc[intval(mb_substr($inLabels[$i]['Zip'], 0, 3))] == $z) {
                        $outList[] = array_merge($inLabels[$i], $NoteText);
                        $inLabels[$i]['Zip'] = -1;
                        $nadc++;
                    }
                }
            }
        }
    }

    unset($inLabels2);
    for ($i = 0; $i < $n; $i++) {
        if ($inLabels[$i]['Zip'] != -1) {
            $inLabels2[] = $inLabels[$i];
        }
    }
    unset($inLabels);
    $inLabels = $inLabels2;

    // Pass 4 looking for remaining Mixed ADC bundles
    $nmadc = 0;
    unset($Zips);
    $n = count($inLabels);
    $zc = $n;
    $NoteText = ['Note' => '******* Presort MIXED ADC '];
    $NameText = ['Name' => '** ' . $zc . ' Addresses in Bundle *'];
    $AddressText = ['Address' => '** ' . $nTotalLabels . ' Total Addresses *'];
    $CityText = ['City' => '******* Presort MIXED ADC   '];
    $outList[] = array_merge($NoteText, $NameText, $AddressText, $CityText);
    for ($i = 0; $i < $n; $i++) {
        $outList[] = array_merge($inLabels[$i], $NoteText);
        $nmadc++;
    }

    if (count($outList) > 0) {
        return $outList;
    } else {
        return 'FALSE';
    }
}

function GenerateLabels(&$pdf, $mode, $iBulkMailPresort, $bToParents, $bOnlyComplete): string
{
    // $mode is "indiv" or "fam"

    $sAdultRole = SystemConfig::getValue('sDirRoleHead') . ',' . SystemConfig::getValue('sDirRoleSpouse');
    $sAdultRole = trim($sAdultRole, " ,\t\n\r\0\x0B");
    $aAdultRole = explode(',', $sAdultRole);
    $aAdultRole = array_unique($aAdultRole);
    sort($aAdultRole);

    $sChildRole = trim(SystemConfig::getValue('sDirRoleChild'), " ,\t\n\r\0\x0B");
    $aChildRole = explode(',', $sChildRole);
    $aChildRole = array_unique($aChildRole);
    sort($aChildRole);

    $sSQL = 'SELECT * FROM person_per LEFT JOIN family_fam ';
    $sSQL .= 'ON person_per.per_fam_ID = family_fam.fam_ID ';
    $sSQL .= 'WHERE per_ID IN (' . convertCartToString($_SESSION['aPeopleCart']) . ') ';
    $sSQL .= 'ORDER BY per_LastName, per_FirstName, fam_Zip';
    $rsCartItems = RunQuery($sSQL);
    $sRowClass = 'RowColorA';
    $didFam = [];

    while ($aRow = mysqli_fetch_array($rsCartItems)) {
        // It's possible (but unlikely) that three labels can be generated for a
        // family even when they are grouped.
        // At most one label for all adults
        // At most one label for all children
        // At most one label for all others (for example, another church or a landscape
        // company)

        $sRowClass = AlternateRowStyle($sRowClass);

        if (($aRow['per_fam_ID'] == 0) && ($mode == 'fam')) {
            // Skip people with no family ID
            continue;
        }

        // Skip if mode is fam and we have already printed labels
        if (array_key_exists($aRow['per_fam_ID'], $didFam) && $didFam[$aRow['per_fam_ID']] && ($mode == 'fam')) {
            continue;
        }

        $didFam[$aRow['per_fam_ID']] = 1;

        unset($aName);

        if ($mode === 'fam') {
            $aName = GroupBySalutation($aRow['per_fam_ID'], $aAdultRole, $aChildRole);
        } else {
            $sName = FormatFullName(
                $aRow['per_Title'],
                $aRow['per_FirstName'],
                '',
                $aRow['per_LastName'],
                $aRow['per_Suffix'],
                1
            );

            $bChild = false;
            foreach ($aChildRole as $value) {
                if ($aRow['per_fmr_ID'] == $value) {
                    $bChild = true;
                }
            }

            if ($bChild) {
                $aName['child'] = mb_substr($sName, 0, 33);
            } else {
                $aName['indiv'] = mb_substr($sName, 0, 33);
            }
        }

        foreach ($aName as $key => $sName) {
            // Bail out if nothing to print
            if ($sName === 'Nothing to return') {
                continue;
            }

            if ($bToParents && ($key === 'child')) {
                $sName = "To the parents of:\n" . $sName;
            }

            SelectWhichAddress($sAddress1, $sAddress2, $aRow['per_Address1'], $aRow['per_Address2'], $aRow['fam_Address1'], $aRow['fam_Address2'], false);

            $sCity = SelectWhichInfo($aRow['per_City'], $aRow['fam_City'], false);
            $sState = SelectWhichInfo($aRow['per_State'], $aRow['fam_State'], false);
            $sZip = SelectWhichInfo($aRow['per_Zip'], $aRow['fam_Zip'], false);

            $sAddress = $sAddress1;
            if ($sAddress2 != '') {
                $sAddress .= "\n" . $sAddress2;
            }

            if (!$bOnlyComplete || (strlen($sAddress) && strlen($sCity) && strlen($sState) && strlen($sZip))) {
                $sLabelList[] = ['Name' => $sName, 'Address' => $sAddress, 'City' => $sCity, 'State' => $sState, 'Zip' => $sZip]; //,'fam_ID'=>$aRow['fam_ID']);
            }
        }
    }

    if ($iBulkMailPresort) {
        // Now sort the label list by presort bundle definitions
        $zipLabels = ZipBundleSort($sLabelList);
        if ($iBulkMailPresort == 2) {
            foreach ($zipLabels as $sLT) {
                $pdf->addPdfLabel(sprintf(
                    "%s\n%s\n%s\n%s, %s %s",
                    $sLT['Note'],
                    $sLT['Name'],
                    $sLT['Address'],
                    $sLT['City'],
                    $sLT['State'],
                    $sLT['Zip']
                ));
            }
        } else {
            foreach ($zipLabels as $sLT) {
                $pdf->addPdfLabel(sprintf(
                    "%s\n%s\n%s, %s %s",
                    $sLT['Name'],
                    $sLT['Address'],
                    $sLT['City'],
                    $sLT['State'],
                    $sLT['Zip']
                ));
            }
        }
    } else {
        foreach ($sLabelList as $sLT) {
            $pdf->addPdfLabel(sprintf(
                "%s\n%s\n%s, %s %s",
                $sLT['Name'],
                $sLT['Address'],
                $sLT['City'],
                $sLT['State'],
                $sLT['Zip']
            ));
        }
    }

    if (isset($zipLabels)) {
        return serialize($zipLabels);
    } else {
        return serialize($sLabelList);
    }
}

// Standard format
$startcol = InputUtils::legacyFilterInput($_GET['startcol'], 'int');
if ($startcol < 1) {
    $startcol = 1;
}

$startrow = InputUtils::legacyFilterInput($_GET['startrow'], 'int');
if ($startrow < 1) {
    $startrow = 1;
}

$sLabelType = InputUtils::legacyFilterInput($_GET['labeltype'], 'char', 8);
setcookie('labeltype', $sLabelType, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);

$pdf = new PdfLabel($sLabelType, $startcol, $startrow);

$sFontInfo = FontFromName($_GET['labelfont']);
setcookie('labelfont', $_GET['labelfont'], ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);
$sFontSize = $_GET['labelfontsize'];
setcookie('labelfontsize', $sFontSize, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);
$pdf->SetFont($sFontInfo[0], $sFontInfo[1]);

if ($sFontSize === 'default') {
    $sFontSize = '10';
}

$pdf->setCharSize($sFontSize);

// Manually add a new page if we're using offsets
if ($startcol > 1 || $startrow > 1) {
    $pdf->addPage();
}

$mode = $_GET['groupbymode'];
setcookie('groupbymode', $mode, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);

if (array_key_exists('bulkmailpresort', $_GET)) {
    $bulkmailpresort = $_GET['bulkmailpresort'];
} else {
    $bulkmailpresort = false;
}

setcookie('bulkmailpresort', $bulkmailpresort, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);

if (array_key_exists('bulkmailquiet', $_GET)) {
    $bulkmailquiet = $_GET['bulkmailquiet'];
} else {
    $bulkmailquiet = false;
}

setcookie('bulkmailquiet', $bulkmailquiet, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);

$iBulkCode = 0;
if ($bulkmailpresort) {
    $iBulkCode = 1;
    if (!$bulkmailquiet) {
        $iBulkCode = 2;
    }
}

$bToParents = (array_key_exists('toparents', $_GET) && $_GET['toparents'] == 1);
setcookie('toparents', $bToParents, ['expires' => time() + 60 * 60 * 24 * 90, 'path' => '/']);

$bOnlyComplete = ($_GET['onlyfull'] == 1);

$sFileType = InputUtils::legacyFilterInput($_GET['filetype'], 'char', 4);

$aLabelList = unserialize(
    GenerateLabels($pdf, $mode, $iBulkCode, $bToParents, $bOnlyComplete)
);

if ($sFileType === 'PDF') {
    if ((int) SystemConfig::getValue('iPDFOutputType') === 1) {
        $pdf->Output('Labels-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.pdf', 'D');
    } else {
        $pdf->Output();
    }
} else {
    // File Type must be CSV
    $delimiter = SystemConfig::getValue('sCSVExportDelimiter');

    $sCSVOutput = '';
    if ($iBulkCode) {
        $sCSVOutput .= '"ZipBundle"' . $delimiter;
    }

    $sCSVOutput .= '"' . InputUtils::translateSpecialCharset('Greeting') . '"' . $delimiter . '"' . InputUtils::translateSpecialCharset('Name') . '"' . $delimiter . '"' . InputUtils::translateSpecialCharset('Address') . '"' . $delimiter . '"' . InputUtils::translateSpecialCharset('City') . '"' . $delimiter . '"' . InputUtils::translateSpecialCharset('State') . '"' . $delimiter . '"' . InputUtils::translateSpecialCharset('Zip') . '"' . "\n";

    foreach ($aLabelList as $sLT) {
        if ($iBulkCode) {
            $sCSVOutput .= '"' . $sLT['Note'] . '"' . $delimiter;
        }

        $iNewline = strpos($sLT['Name'], "\n");
        if ($iNewline === false) { // There is no newline character
            $sCSVOutput .= '""' . $delimiter . '"' . InputUtils::translateSpecialCharset($sLT['Name']) . '"' . $delimiter;
        } else {
            $sCSVOutput .= '"' . InputUtils::translateSpecialCharset(mb_substr($sLT['Name'], 0, $iNewline)) . '"' . $delimiter .
                            '"' . InputUtils::translateSpecialCharset(mb_substr($sLT['Name'], $iNewline + 1)) . '"' . $delimiter;
        }

        $iNewline = strpos($sLT['Address'], "\n");
        if ($iNewline === false) { // There is no newline character
            $sCSVOutput .= '"' . InputUtils::translateSpecialCharset($sLT['Address']) . '"' . $delimiter;
        } else {
            $sCSVOutput .= '"' . InputUtils::translateSpecialCharset(mb_substr($sLT['Address'], 0, $iNewline)) . '"' . $delimiter .
                            '"' . InputUtils::translateSpecialCharset(mb_substr($sLT['Address'], $iNewline + 1)) . '"' . $delimiter;
        }

        $sCSVOutput .= '"' . InputUtils::translateSpecialCharset($sLT['City']) . '"' . $delimiter .
                        '"' . InputUtils::translateSpecialCharset($sLT['State']) . '"' . $delimiter .
                        '"' . $sLT['Zip'] . '"' . "\n";
    }

    header('Content-type: application/csv;charset=' . SystemConfig::getValue('sCSVExportCharset'));
    header('Content-Disposition: attachment; filename=Labels-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Add BOM to fix UTF-8 in Excel 2016 but not under, so the problem is solved with the sCSVExportCharset variable
    if (SystemConfig::getValue('sCSVExportCharset') == 'UTF-8') {
        echo "\xEF\xBB\xBF";
    }

    echo $sCSVOutput;
}

exit;
