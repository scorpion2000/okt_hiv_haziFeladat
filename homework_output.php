<?php

/*
*   Elnézést az angol-magyar keverésért
*   Tipikusan angolul programozok, de a feladat magyarul van, kicsit belezavart a munkamenetembe
*/

include 'homework_input.php';

function validate($vizsgazo) {
    $points = 0;
    $pointsExtra = 0;
    global $schools;    //  whacky megoldás
    $school = $schools[$vizsgazo->getSzak()->getEgyetem()];

    //  A feladat szerint kötelező magyar nyelv és irodalom, történelem és matematika tárgyakból az érettségi
    //  a feladat végén visztont x egyetemnek csak egy kötelező érettségilye van
    //  Nem tudom eldönteni hogy akkor az első szabályt kell követni, vagy az egyetem specifikusat, szóval megyek az elsővel
    $searchTotal = 0;
    foreach ($vizsgazo->getEredmenyek() as $eredmeny) {
        if ($eredmeny->getNev() == "magyar nyelv és irodalom" && (int)$eredmeny->getEredmeny() >= 20) $searchTotal++;
        if ($eredmeny->getNev() == "történelem" && (int)$eredmeny->getEredmeny() >= 20) $searchTotal++;
        if ($eredmeny->getNev() == "matematika" && (int)$eredmeny->getEredmeny() >= 20) $searchTotal++;
    }
    if ($searchTotal < 3) return "fail";

    if (!$school->validateVizsgazo($vizsgazo))
        return "fail";
    
    $pointsArray = array();
    $pointsArray = $school->calculatePoints($vizsgazo);
    if ($pointsArray == "fail")
        return "fail";
    $points = $pointsArray['points'];
    $pointsExtra = $pointsArray['pointsExtra'];
    
    //  Magic
    $nyelvSzintek = [
        1 => "A1",
        2 => "A2",
        3 => "B1",
        4 => "B2",
        5 => "C1",
        6 => "C2"
    ];

    $tobbletpontNyelvek = array();
    foreach ($vizsgazo->getTobbletpontok() as $tobbletpont) {
        $removePrev = false;
        if (in_array($tobbletpont->getNyelv(), $tobbletpontNyelvek))
        {
            //  array_search visszaadja a kulcsot. A $nyelvSzintek tömbben pedig nagysági sorrendben vannak a típusok
            if (array_search($tobbletpont->getTipus(), $nyelvSzintek) <= array_search($tobbletpontNyelvek[$tobbletpont->getNyelv()], $nyelvSzintek))
                continue;
            else {
                switch ($tobbletpontNyelvek[$tobbletpont->getNyelv()]) {
                    case 'B2':
                        if ($removePrev)
                        $pointsExtra -= 28;
                        $pointsExtra = min($pointsExtra, 0);
                        break;
        
                    case 'C1':
                        $pointsExtra -= 40;
                        $pointsExtra = min($pointsExtra, 0);
                        break;
                    
                    default:
                        return "fail";
                        break;
                }

                $tobbletpontNyelvek[$tobbletpont->getNyelv()] = $tobbletpont->getTipus();
            }
        } else {
            $tobbletpontNyelvek[$tobbletpont->getNyelv()] = $tobbletpont->getTipus();
        }

        //  A feladat csak ezt a kettőt kéri
        switch ($tobbletpont->getTipus()) {
            case 'B2':
                $pointsExtra += 28;
                $pointsExtra = min($pointsExtra, 100);
                break;

            case 'C1':
                $pointsExtra += 40;
                $pointsExtra = min($pointsExtra, 100);
                break;
            
            default:
                return "fail";
                break;
        }
    }

    //  Adhatnánk részletesebb választ is arra hogy miért nem kap felvételt valaki, de a felvi.hu pontszámlálója sem teszi meg ezt
    return $points + $pointsExtra;
}

class iskola {
    private $nev;
    private $karok;
    private $mandatory;
    private $isMandatoryHigh;
    private $mandatoryPicks;

    function __construct($nev) {
        $this->nev = $nev;
        $this->karok = array();
        $this->isMandatoryHigh = false;
    }

    function setMandatory($mandatory) {
        $this->mandatory = $mandatory;
    }

    function setMandatoryPicks($picksArray) {
        $this->mandatoryPicks = array();
        foreach ($picksArray as $value) {
            array_push($this->mandatoryPicks, $value);
        }
    }

    function addKar($kar) {
        array_push($this->karok, $kar);
    }

    function setMandatoryHigh($boolValue) {
        $this->isMandatoryHigh = $boolValue;
    }

    function getName() {
        return $this->nev;
    }

    function validateVizsgazo($vizsgazo) {
        $hasMandatory = false;
        $hasmandatoryPick = false;
        if (in_array($vizsgazo->getSzak()->getKar(), $this->karok)) {
            foreach ($vizsgazo->getEredmenyek() as $eredmeny) {
                $nev = $eredmeny->getNev();
    
                if ($this->isMandatoryHigh) {
                    if ($nev == $this->mandatory && $eredmeny->getTipus() == "emelt") $hasMandatory = true;
                } else if ($nev == $this->mandatory) $hasMandatory = true;

                if (in_array($nev, $this->mandatoryPicks))
                    $hasmandatoryPick = true;
            }
        }
    
        if ($hasMandatory && $hasmandatoryPick)
            return true;
        else
            return false;
    }

    function calculatePoints($vizsgazo) {
        $pointsM = 0;   //  mandatory
        $pointsS = 0;   //  secondary
        $pointsExtra = 0;

        foreach ($vizsgazo->getEredmenyek() as $eredmeny) {
            if ($eredmeny->getNev() == $this->mandatory)
            {
                if ((int)$eredmeny->getEredmeny() <= 20)
                    return "fail";

                if ((int)$eredmeny->getEredmeny() > $pointsM)
                    $pointsM = (int)$eredmeny->getEredmeny();
            }

            if (in_array($eredmeny->getNev(), $this->mandatoryPicks)) {
                if ((int)$eredmeny->getEredmeny() > $pointsS)
                    $pointsS = (int)$eredmeny->getEredmeny();
            }

            if ($eredmeny->getTipus() == "emelt") $pointsExtra += 50;
            $pointsExtra = min($pointsExtra, 100);
        }


        $returnArray = ['points' => min(($pointsM + $pointsS) * 2, 400), 'pointsExtra' => $pointsExtra];

        return $returnArray;
    }
}

class vizsgazo {
    private $szak;
    private $eredmenyek;
    private $tobbletpontok;

    function __construct($dataSet) {
        $this->szak = new szak($dataSet['valasztott-szak']);

        $this->eredmenyek = array();
        foreach ($dataSet['erettsegi-eredmenyek'] as $value) {
            array_push($this->eredmenyek, new eredmeny($value));
        }

        $this->tobbletpontok = array();
        foreach ($dataSet['tobbletpontok'] as $value) {
            array_push($this->tobbletpontok, new tobbletpont($value));
        }
    }

    function getSzak() {
        return $this->szak;
    }

    function getEredmenyek() {
        return $this->eredmenyek;
    }

    function getTobbletpontok() {
        return $this->tobbletpontok;
    }
}

class szak {
    private $egyetem;
    private $kar;
    private $szak;

    function __construct($dataSet) {
        $this->egyetem = $dataSet['egyetem'];
        $this->kar = $dataSet['kar'];
        $this->szak = $dataSet['szak'];
    }

    function getEgyetem() {
        return $this->egyetem;
    }

    function getKar() {
        return $this->kar;
    }

    function getSzak() {
        return $this->szak;
    }
}

class eredmeny {
    private $nev;
    private $tipus;
    private $eredmeny;

    function __construct($dataSet) {
        $this->nev = $dataSet['nev'];
        $this->tipus = $dataSet['tipus'];
        $this->eredmeny = $dataSet['eredmeny'];
    }

    function getNev() {
        return $this->nev;
    }

    function getTipus() {
        return $this->tipus;
    }

    function getEredmeny() {
        return $this->eredmeny;
    }
}

class tobbletpont {
    private $kategoria;
    private $tipus;
    private $nyelv;

    function __construct($dataSet) {
        $this->kategoria = $dataSet['kategoria'];
        $this->tipus = $dataSet['tipus'];
        $this->nyelv = $dataSet['nyelv'];
    }

    function getKategoria() {
        return $this->kategoria;
    }

    function getTipus() {
        return $this->tipus;
    }

    function getNyelv() {
        return $this->nyelv;
    }
}

$vizsgazok = array();
$schools = array(); //  Elismerem, ez nem a legjobb megoldás

//  Képzeljük el hogy ezt a részt egy adatbézisból vesszük ki
//  Példa mintákban csak ELTE van
//  feltételezem "PPKE" a másik egyetem kódneve
$schools["ELTE"] = new iskola("ELTE");
$schools["PPKE"] = new iskola("PPKE");

$schools["ELTE"]->addKar("IK");
$schools["PPKE"]->addKar("BTK");

$schools["ELTE"]->setMandatory("matematika");
$schools["PPKE"]->setMandatory("angol");
$schools["PPKE"]->setMandatoryHigh(true);

$picks = [
    "biológia",
    "fizika",
    "informatika",
    "kémia"
];
$schools["ELTE"]->setMandatoryPicks($picks);
$picks = [
    "francia",
    "német",
    "olasz",
    "orosz",
    "spanyol",
    "történelem"
];
$schools["PPKE"]->setMandatoryPicks($picks);

//  A példában kéteszer szerepel az $exampleData változó, ezzel felülírva egymást.
//  Így kaptam meg a példát. Nem tudom hogy ennek van-e oka, minden esetre nem nyúltam hozzá
array_push($vizsgazok, new vizsgazo($exampleData));
array_push($vizsgazok, new vizsgazo($exampleData2));
array_push($vizsgazok, new vizsgazo($exampleData3));

//  A "vizsgázóknak" nincsen nevük, így észben kell tartani a $vizsgazok tömb sorrendjét
foreach ($vizsgazok as $vizsgazo) {
    $result = validate($vizsgazo);
    if ($result == "fail") {
        echo "A vizsgázó nem jutott be </br>";
    } else {
        echo "A vizsgázó $result pontot szerzett</br>";
    }
}


?>