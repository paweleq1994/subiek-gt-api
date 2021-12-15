<?php
namespace APISubiektGT\SubiektGT;
use COM;
use Exception;
use APISubiektGT\Logger;
use APISubiektGT\MSSql;
use APISubiektGT\SubiektGT\SubiektObj;
use APISubiektGT\SubiektGT\Product;
use APISubiektGT\SubiektGT\Customer;
use APISubiektGT\Helper;

class Document extends SubiektObj {
    protected $documentGt;
    protected $products = false;
    protected $fiscal_state = false;
    protected $accounting_state = false;
    protected $reference;
    protected $comments;
    protected $customer = array();
    protected $doc_ref = '';
    protected $amount = 0;
    protected $state = -1;
    protected $date_of_delivery = '';
    protected $doc_type = '';
    protected $doc_type_id = 0;
    protected $documentDetail= array();
    protected $order_processing = 0;
    protected $id_flag = NULL;
    protected $id_gr_flag = NULL;
    protected $flag_name = '';
    protected $flag_comment = '';


    public function __construct($subiektGt,$documentDetail = array()){
        parent::__construct($subiektGt, $documentDetail);
        $this->excludeAttr(array('documentGt','documentDetail','doc_types'));
        if($this->doc_ref!='' && $subiektGt->SuDokumentyManager->Istnieje($this->doc_ref)){
            $this->documentGt = $subiektGt->SuDokumentyManager->Wczytaj($this->doc_ref);
            $this->getGtObject();
            $this->is_exists = true;
        }
        $this->documentDetail = $documentDetail;
    }



    protected function setGtObject(){
        return false;
    }

    public function getPdf(){
        $temp_dir = sys_get_temp_dir();
        if($this->is_exists){
            $file_name = $temp_dir.'/'.$this->gt_id.'.pdf';
            $this->documentGt->DrukujDoPliku($file_name,0);
            $pdf_file = file_get_contents($file_name);
            unlink($file_name);
            Logger::getInstance()->log('api','Wygenerowano pdf dokumentu: '.$this->doc_ref ,__CLASS__.'->'.__FUNCTION__,__LINE__);
            return array('encoding'=>'base64',
                'doc_ref'=>$this->doc_ref,
                'is_exists' => $this->is_exists,
                'file_name' => mb_ereg_replace("[ /]","_",$this->doc_ref.'.pdf'),
                'state' => $this->state,
                'accounting_state' => $this->accounting_state,
                'fiscal_state' => $this->fiscal_state,
                'doc_type' => $this->doc_type,
                'pdf_file'=>base64_encode($pdf_file));
        }
        return false;
    }


    public function getState(){
        return array('doc_ref'=>$this->doc_ref,
            'is_exists' => $this->is_exists,
            'doc_type' => $this->doc_type,
            'state' => $this->state,
            'accounting_state' => $this->accounting_state,
            'fiscal_state' => $this->fiscal_state,
            'order_processing' => $this->order_processing,
            'id_flag'	 	=> $this->id_flag,
            'flag_name'	=> $this->flag_name,
            'flag_comment'		=> $this->flag_comment,
            'amount'		=> $this->amount
        );
    }

    protected function getGtObject(){
        if(!$this->documentGt){
            return false;
        }
        $this->gt_id = $this->documentGt->Identyfikator;
        $this->fiscal_state = $this->documentGt->StatusFiskalny;
        $this->accounting_state = $this->documentGt->StatusKsiegowy;
        $this->doc_type = $this->doc_types[$this->documentGt->Typ];
        $this->doc_type_id = $this->documentGt->Typ;

        $o = $this->getDocumentById($this->gt_id);

        $this->reference =  $o['dok_NrPelnyOryg'];
        $this->comments = $o['dok_Uwagi'];
        $this->doc_ref = $o['dok_NrPelny'];
        $this->state = $o['dok_Status'];
        $this->amount = $o['dok_WartBrutto'];
        $this->date_of_delivery = $o['dok_TerminRealizacji'];
        $this->order_processing = $o['dok_PrzetworzonoZKwZD'];
        if(is_null($this->id_gr_flag)){
            $this->id_flag = $o['flg_Id'];
            $this->flag_name = $o['flg_Text'];
            $this->id_gr_flag = $o['flg_IdGrupy'];
            $this->flag_comment = $o['flw_Komentarz'];
        }

        if(!is_null($this->documentGt->KontrahentId)){
            $customer = Customer::getCustomerById($this->documentGt->KontrahentId);
            $this->customer = $customer;
        }

        $positions = array();
        for($i=1; $i<=$this->documentGt->Pozycje->Liczba(); $i++){
            $positions[$this->documentGt->Pozycje->Element($i)->Id]['name'] = $this->documentGt->Pozycje->Element($i)->TowarNazwa;
            $positions[$this->documentGt->Pozycje->Element($i)->Id]['code'] = $this->documentGt->Pozycje->Element($i)->TowarSymbol;
        }


        $products = $this->getPositionsByOrderId($this->gt_id);
        foreach($products as $p){
            $p_a = array('name'=> $positions[$p['ob_Id']]['name'],
                'code'=> $positions[$p['ob_Id']]['code'],
                'qty'=>$p['ob_Ilosc'],
                'price'=>$p['ob_WartBrutto']);
            $this->products[] = $p_a;
        }

    }

    protected function getDocumentById($id){
        $sql = "SELECT * FROM dok__Dokument as d
					LEFT JOIN fl_Wartosc as fw ON (fw.flw_IdObiektu = d.dok_Id)
					LEFT JOIN fl__Flagi as f ON (f.flg_Id = fw.flw_IdFlagi)
				WHERE dok_Id = {$id}";
        $data = MSSql::getInstance()->query($sql);
        return $data[0];
    }

    protected function getPositionsByOrderId($id){
        $sql = "SELECT * FROM dok_Pozycja
			   WHERE ob_DokHanId = {$id}";
        $data = MSSql::getInstance()->query($sql);
        return $data;
    }

    public function delete(){
        if(!$this->documentGt){
            return false;
        }

        $this->documentGt->Usun(false);
        return array('doc_ref'=>$this->doc_ref);
    }

    public function setFlag(){
        if(!$this->is_exists){
            return false;
        }
        parent::flag(intval($this->id_gr_flag),$this->flag_name,'');
        return array('doc_ref'=>$this->doc_ref,
            'flag_name'=>$this->flag_name,
            'id_gr_flag' => $this->id_gr_flag);
    }

    public function getProductIdByCode($symbol){
        $sql = "SELECT tw_Id FROM vwTowar where tw_Symbol = '$symbol'";
        $data = MSSql::getInstance()->query($sql);
        return $data[0]["tw_Id"];
    }

    public function addMM(){
        $oDok = $this->subiektGt->SuDokumentyManager->DodajMM();

        foreach ($this->products as $produkt){
            if($produkt["code"]!='' &&  $this->subiektGt->TowaryManager->IstniejeWg($produkt["code"],2)) {
                $oSubPoz = $oDok->Pozycje->Dodaj(Document::getProductIdByCode($produkt["code"]));
                $oSubPoz->IloscJm = $produkt["qty"];
            }
        }

        $oDok->MagazynOdbiorczyId = $this->mag_to_id;
        $oDok->MagazynNadawczyId = $this->mag_from_id;

        try{
            $oDok->ZapiszSymulacja();
        }catch(Exception $e){
            if($oDok->PozycjeBrakujace->Liczba()>0){
                $braki =  $oDok->PozycjeBrakujace;
                foreach ($braki as $brak){
                    echo "Pozycja na zamówieniu: " . $brak->Lp . "<br>";
                    echo "Nazwa towaru: " . iconv( "Windows-1250", "UTF-8", $brak->TowarNazwa) . "<br>";
                    echo "Wybrano: " . $brak->IloscJm . "<br>";
                    echo "Brakuje: " . $brak->Brak . "<br>";
                    echo "Na magazynie: " . $brak->MagazynStan . "<br>";
                    echo "Zarezerwowane: " . $brak->MagazynRezerwacja . "<br><br>";
                }
                return array(
                    'doc_ref' => $oDok->NumerPelny,
                    'doc_state' => 'warning',
                    'doc_state_code' => 2,
                    'message' => 'Nie można utworzyć dokumentu MM. Brakuje produktów na magazynie.',
                );
            }else{
                throw new Exception('Nie można utworzyć dokumentu MM. Dokument: '.$this->order_ref.'. '.$this->toUtf8($e->getMessage()));
            }
        }

        $oDok->Zapisz();

        return array('doc_state' => 'success', 'doc_nr' => $oDok->NumerPelny);
    }


    public function addPW(){
        $oDok = $this->subiektGt->SuDokumentyManager->DodajPW();

        foreach ($this->products as $produkt){
            if($produkt["code"]!='' &&  $this->subiektGt->TowaryManager->IstniejeWg($produkt["code"],2)) {
                $oSubPoz = $oDok->Pozycje->Dodaj(Document::getProductIdByCode($produkt["code"]));
                $oSubPoz->IloscJm = $produkt["qty"];
            }
        }

        try{
            $oDok->ZapiszSymulacja();
        }catch(Exception $e){
            throw new Exception('Nie można utworzyć dokumentu PW. Dokument: '.$oDok->NumerPelny.'. '.$this->toUtf8($e->getMessage()));
        }

        $oDok->Zapisz();

        return array('doc_state' => 'success', 'doc_nr' => $oDok->NumerPelny);
    }

    public function addRW(){
        $oDok = $this->subiektGt->SuDokumentyManager->DodajRW();

        foreach ($this->products as $produkt){
            if($produkt["code"]!='' &&  $this->subiektGt->TowaryManager->IstniejeWg($produkt["code"],2)) {
                $oSubPoz = $oDok->Pozycje->Dodaj(Document::getProductIdByCode($produkt["code"]));
                $oSubPoz->IloscJm = $produkt["qty"];
            }
        }

        try{
            $oDok->ZapiszSymulacja();
        }catch(Exception $e) {
            if ($oDok->PozycjeBrakujace->Liczba() > 0) {
                $braki = $oDok->PozycjeBrakujace;
                foreach ($braki as $brak) {
                    echo "Pozycja na zamówieniu: " . $brak->Lp . "<br>";
                    echo "Nazwa towaru: " . iconv("Windows-1250", "UTF-8", $brak->TowarNazwa) . "<br>";
                    echo "Wybrano: " . $brak->IloscJm . "<br>";
                    echo "Brakuje: " . $brak->Brak . "<br>";
                    echo "Na magazynie: " . $brak->MagazynStan . "<br>";
                    echo "Zarezerwowane: " . $brak->MagazynRezerwacja . "<br><br>";
                }
                return array(
                    'doc_ref' => $oDok->NumerPelny,
                    'doc_state' => 'warning',
                    'doc_state_code' => 2,
                    'message' => 'Nie można utworzyć dokumentu RW. Brakuje produktów na magazynie.',
                );
            } else {
                throw new Exception('Nie można utworzyć dokumentu RW. Dokument: ' . $oDok->NumerPelny . '. ' . $this->toUtf8($e->getMessage()));
            }
        }

        $oDok->Zapisz();

        return array('doc_state' => 'success', 'doc_nr' => $oDok->NumerPelny);
    }


    public function disassembleSet()
    {
        $code = $this->objDetail["products"][0]["code"];
        $qty = $this->objDetail["products"][0]["qty"];

		  if (!$qty > 0) throw new Exception("Nie podana ilosc.");
		  if ($code == '' || !$this->subiektGt->TowaryManager->IstniejeWg($code, 2))	throw new Exception("Produkt nie istnieje.");

        $sql = "select tT.tw_Rodzaj, tK.*, tT2.tw_Symbol from tw__Towar tT left join tw_Komplet as tK on tK.kpl_IdKomplet = tT.tw_Id left join tw__Towar as tT2 on tT2.tw_Id = tK.kpl_IdSkladnik where tT.tw_Symbol = '$code'";
        $data = MSSql::getInstance()->query($sql);
		  if  ($data[0]['tw_Rodzaj'] != 8 )	throw new Exception("Produkt nie jest kompletem.");
		  if (!$data[0]['kpl_Liczba'] > 0 )	throw new Exception("Produkt nie ma składników.");

        try{
	        $oDok = $this->subiektGt->SuDokumentyManager->DodajRW();
	        $oSubPoz = $oDok->Pozycje->Dodaj($this->getProductIdByCode($code));
	        $oSubPoz->IloscJm = $qty;
			  $oSubPoz->WartoscNettoPoRabacie =  $oSubPoz->CenaMagazynowa;
	        $oDok->ZapiszSymulacja();
        } catch(\Exception $e) {
            if($oDok->PozycjeBrakujace->Liczba()>0) throw new Exception('Brakuje produktów na magazynie.');
            	else throw new Exception('Nie można utworzyć dokumentu. Dokument: '.$this->order_ref.'. '.$this->toUtf8($e->getMessage()));

        }
        $oDok->Zapisz();
        $rw = $oDok;
		  $rw_numer = $oDok->NumerPelny;
		  $wart = floatval($oDok->WartoscMagazynowa);

        try {
            $oDok = $this->subiektGt->SuDokumentyManager->DodajPW();
				$oDok->DoDokumentuNumerPelny = $rw_numer;
				$last =  sizeof($data);
				$nr = 1;
				$wart_all = 0;
				$il_pozycji = 0;
            foreach ($data as $skladnik) {
                if ($skladnik["tw_Symbol"] != '' && $this->subiektGt->TowaryManager->IstniejeWg($skladnik["tw_Symbol"], 2))
					 {
                    $oSubPoz = $oDok->Pozycje->Dodaj($skladnik["kpl_IdSkladnik"]);
                    $oSubPoz->IloscJm = intval($skladnik["kpl_Liczba"]) * $qty;

					    $oSubPoz->WartoscNettoPoRabacie =  floatval($oSubPoz->CenaMagazynowa) * floatval($oSubPoz->IloscJm);
						 $oSubPoz->CenaNettoPoRabacie  = floatval($oSubPoz->CenaMagazynowa);
						 $wart_all = floatval($wart_all) + floatval($oSubPoz->WartoscNettoPoRabacie);
						 $il_pozycji = floatval($il_pozycji) + floatval($oSubPoz->IloscJm);
                } else {
                    $rw->Usun();
                    throw new Exception("Produkt " . $skladnik["tw_Symbol"] . " nie istnieje.");
                }
					$nr++;
            }

				$roznica = (floatval($wart) - floatval($wart_all)) / floatval($il_pozycji);
				for ($x = 1; $x <= $oDok->Pozycje->Liczba(); $x++) {
					$new_cena =  floatval($oDok->Pozycje->Element($x)->WartoscNettoPoRabacie) + floatval($roznica)*floatval($oDok->Pozycje->Element($x)->IloscJm);
					$oDok->Pozycje->Element($x)->WartoscNettoPoRabacie = $new_cena;
				}

            try {
	             $oDok->Przelicz();
                $oDok->ZapiszSymulacja();
            } catch (\Exception $e) {
                $rw->Usun(false);
                throw new Exception('Nie można utworzyć dokumentu PW.');
            }
            $oDok->Zapisz();

        } catch (\Exception $e) {
            $rw->Usun(false);
            throw new Exception('Nie można utworzyć dokumentu PW.');
        }
		  $rw->DoDokumentuNumerPelny =  $oDok->NumerPelny;
		  $rw->Zapisz();

        return 'Komplet '.$code.' został zdezmontowany';
    }


    public function assembleSet()
    {
        $code = $this->objDetail["products"][0]["code"];
        $qty = $this->objDetail["products"][0]["qty"];

		  if (!$qty > 0) throw new Exception("Nie podana ilosc.");
		  if ($code == '' || !$this->subiektGt->TowaryManager->IstniejeWg($code, 2))	throw new Exception("Produkt nie istnieje.");


        $sql = "select tT.tw_Rodzaj, tK.*, tT2.tw_Symbol from tw__Towar tT left join tw_Komplet as tK on tK.kpl_IdKomplet = tT.tw_Id left join tw__Towar as tT2 on tT2.tw_Id = tK.kpl_IdSkladnik where tT.tw_Symbol = '$code'";
        $data = MSSql::getInstance()->query($sql);
		  if  ($data[0]['tw_Rodzaj'] != 8 )	throw new Exception("Produkt nie jest kompletem.");
		  if (!$data[0]['kpl_Liczba'] > 0 )	throw new Exception("Produkt nie ma składników.");

        try
		  {
	        $oDok = $this->subiektGt->SuDokumentyManager->DodajRW();
			  $oDok -> PoziomCenyId	= 0;
	        foreach ($data as $skladnik)
			  {
	          if ($skladnik["tw_Symbol"] != '' && $this->subiektGt->	TowaryManager->IstniejeWg($skladnik["tw_Symbol"], 2))
				 {
             	$oSubPoz = $oDok->Pozycje->Dodaj($skladnik["kpl_IdSkladnik"]);
               $oSubPoz->IloscJm = intval($skladnik["kpl_Liczba"]) * $qty;
					$oSubPoz->WartoscNettoPoRabacie =  floatval($oSubPoz->CenaMagazynowa) * floatval($oSubPoz->IloscJm);
					$oSubPoz->CenaNettoPoRabacie =  floatval($oSubPoz->CenaMagazynowa);

             } else throw new Exception("Produkt " . $skladnik["tw_Symbol"] . " nie istnieje.");
		     }
			  $oDok->Przelicz();
           $oDok->ZapiszSymulacja();
        }catch(\Exception $e){
            if($oDok->PozycjeBrakujace->Liczba()>0) throw new Exception('Nie można utworzyć dokumentu RW. Brakuje produktów na magazynie.');
            else throw new Exception('Nie można utworzyć dokumentu RW.');
        }
        $oDok->Zapisz();

        $rw = $oDok;

		  $wart = $oDok->WartoscMagazynowa;
		  $rw_numer = $oDok->NumerPelny;
        $result = array('doc_state' => 'success', 'rw_doc_nr' => $oDok->NumerPelny);

		  try {
	        $oDok = $this->subiektGt->SuDokumentyManager->DodajPW();
			  $oDok->DoDokumentuNumerPelny = $rw_numer;
	        $oSubPoz = $oDok->Pozycje->Dodaj($this->getProductIdByCode($code));
	        $oSubPoz->IloscJm = $qty;
			  $oSubPoz->WartoscNettoPoRabacie = $wart;
			  $oDok->Przelicz();
	        try {
	            $oDok->ZapiszSymulacja();
	        } catch (\Exception $e) {
	            $rw->Usun();
	            throw new Exception('Nie można utworzyć dokumentu PW.');
	        }

		  $oDok->Zapisz();
        } catch (\Exception $e) {
            $rw->Usun();
            throw new Exception('Nie można utworzyć dokumentu PW.');
        }
		  $rw->DoDokumentuNumerPelny =  $oDok->NumerPelny;
		  $rw->Zapisz();
        return 'Komplet '.$code.' został zmontowany';
    }


    public function add(){
        return true;
    }

    public function update(){
        return true;
    }

    public function getGt(){
        return $this->documentGt;
    }

}
?>