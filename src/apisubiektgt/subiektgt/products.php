<?php

namespace APISubiektGT\SubiektGT;
use COM;
use APISubiektGT\MSSql;
use APISubiektGT\Logger;
use APISubiektGT\SubiektGT\SubiektObj;
use APISubiektGT\SubiektGT;
use APISubiektGT\Helper;

class Products
{
    protected $subiektGt = false;
	 protected $errors_arr = array();

    public function __construct($subiektGt, $objDetail = array())
    {
        $this->readData($objDetail);
        $this->subiektGt = $subiektGt;
    }

    protected function readData($objDetail)
    {

        if (is_array($objDetail)) {
            foreach ($objDetail as $key => $value) {
                    $this->{$key} = $value;
            }
            $this->objDetail = $objDetail;
        }
    }

	static public function toUtf8($value){
		return Helper::toUtf8($value);
	}

    public function setCfg($cfg)
    {
        $this->cfg = $cfg;
    }

    public function add()
    {

        $i = 0;
        $counter['insert_towar'] = 0;
        $counter['update_towar'] = 0;
		  $errors = 0;

        foreach ($this->objDetail as $towar) {
            $i++;
            $code = $towar['code'];
            $rodzaj = $towar['rodzaj'];
            $action = 'insert_towar';
            try {

            if ($code != '' && $this->subiektGt->Towary->Istnieje($code)) {
                $this->productGt = $this->subiektGt->Towary->Wczytaj($code);
                if (($towar['aktywny'] == true) and ($towar['aktywny'] != $this->productGt->Aktywny))
					 {
                    $this->productGt->Aktywny = true;
                    $this->productGt->Zapisz();
                }
                $action = 'update_towar';
            } else {
                if ($rodzaj == 1) $this->productGt = $this->subiektGt->TowaryManager->DodajTowar();
                elseif ($rodzaj == 2) $this->productGt = $this->subiektGt->TowaryManager->DodajUsluge();
                elseif ($rodzaj == 8) $this->productGt = $this->subiektGt->TowaryManager->DodajKomplet();
            }
                if ($towar['aktywny'] == false) $this->productGt->Aktywny = $towar['aktywny'];
                $name = substr($towar['name'], 0, 50);
					 $this->productGt->Nazwa = Helper::toWin($name);
					 $this->productGt->Opis = Helper::toWin($towar['description']);
					 $name = substr($towar['name_for_devices'], 0, 40);
					 $this->productGt->NazwaDlaUF = Helper::toWin($name);

                $this->productGt->Symbol = $towar['code'];
                $this->productGt->Ceny->Element(1)->Brutto = floatval($towar['price']);
                $this->productGt->Ceny->Element(2)->Brutto = floatval($towar['wholesale_price']);
                $this->productGt->SprzedazVatId = $towar['vat'];
                $this->productGt->GrupaId = $towar['group_id'];
                $this->productGt->Uwagi = Helper::toWin($towar['uwagi']);
                foreach ($towar['attribute'] as $value) {
                    $this->productGt->Cechy->Dodaj($value);
                }

                $id = 1;
                foreach ($towar['pola_dodatkowe'] as $value) {
					 	  if (strlen($value) > 0)
						  {
                    $pole = "Pole" . $id;
                    $cvalue = Helper::toWin($value);
                    $this->productGt->{$pole} = $cvalue;
						  }
                    $id++;
                }
                $this->productGt->OznaczenieJpkVat = $towar['JpkVat'];
                $this->productGt->SymbolUDostawcy = substr(sprintf('%s', $towar['supplier_code']), 0, 20);
                $ean = substr(sprintf('%s', trim($towar['ean'])), 0, 20);
                $this->productGt->KodyKreskowe->Podstawowy = $ean;

                $this->productGt->Zapisz();
	             $counter[$action]++;

            } catch (\Exception  $e) {
                $e_message = strip_tags($e->getMessage().' Line:'.$e->getLine ());
					 $e_message = str_replace(array("\n", "\r"), '', $e_message);
                $this->errors_arr[] =  array("id: ".$towar["code"], "aktywny: ".$towar['aktywny'], trim($e_message));
					 $errors++;
            }
        }

        return array($i,$counter,$errors, $this->errors_arr);

    }

    public function updatePrice()
    {
        $i = 0;
        $counter['update'] = 0;
		  $counter['brak'] = 0;
        $errors = 0;

        foreach ($this->objDetail as $towar) {
		  		$action = 'brak';
            $code = $towar['code'];
            try {
                if ($code != '' && $this->subiektGt->TowaryManager->IstniejeWg($code, 2)) {
                    $this->productGt = $this->subiektGt->TowaryManager->WczytajTowarWg($code, 2);
                    if (($towar['aktywny'] == true) and ($towar['aktywny'] != $this->productGt->Aktywny)) {
                        $this->productGt->Aktywny = true;
                        $this->productGt->Zapisz();
                    }

						  $price = round($towar['price'],2);
						  $price=str_replace('.',',',$price);
                    $wholesale_price = round($towar['wholesale_price'],2);
						  $wholesale_price=str_replace('.',',',$wholesale_price);

						  if (($this->productGt->Ceny->Element(1)->Brutto != $price) or ($this->productGt->Ceny->Element(2)->Brutto != $wholesale_price))
						  {
							  $this->productGt->Ceny->Element(1)->Brutto = $price;
	                    $this->productGt->Ceny->Element(2)->Brutto = $wholesale_price;

	                    $this->productGt->Zapisz();
			 		  		  $action = 'update';
						  }
                    $i++;
                }
            } catch (\Exception  $e) {
                $e_message = strip_tags($e->getMessage() . ' Line:' . $e->getLine());
                $e_message = str_replace(array("\n", "\r"), '', $e_message);
                $this->errors_arr[] = array("id: " . $towar["code"], "aktywny: " . $towar['aktywny'], trim($e_message));
                $errors++;
            }
            $counter[$action]++;
        }
        return array("counter"=>$counter, "total"=>$i, "errors"=>$errors, "errors_info"=>$this->errors_arr);
    }

    public function updateKomplety()
    {
        $i = 0;
        $counter['brak'] = 0;
        $counter['insert'] = 0;
        $counter['to_update'] = 0;
        $errors = 0;
        $do_recznej_edycji = [];

        foreach ($this->objDetail as $towar) {
            $code = $towar['code'];
            $action = "brak";
            try {
                if ($code != '' && $this->subiektGt->TowaryManager->IstniejeWg($code, 2)) {
                    $this->productGt = $this->subiektGt->TowaryManager->WczytajTowarWg($code, 2);
                    if (($towar['aktywny'] == true) and ($towar['aktywny'] != $this->productGt->Aktywny)) {
                        $this->productGt->Aktywny = true;
                        $this->productGt->Zapisz();
                    }
                    $ilosc = $this->productGt->Skladniki->Liczba;

                    if ($ilosc == 0) {
                        foreach ($towar['tablica'] as $value) {
                            $skladnik = $this->productGt->Skladniki->Dodaj($value['element']);
                            $skladnik->Ilosc = $value['ilosc'];
                        }
                        $this->productGt->Zapisz();
                        $action = "insert";

                    } else
						  {
                       $symbole_towaru_w_komplecie = array();
                       foreach ($towar['tablica'] as $value) {
							  	 $symbole_towaru_w_komplecie[$value["element"]]['ilosc'] = $value["ilosc"];
							  	 $symbole_towaru_w_komplecie[$value["element"]]['checked'] = 0;
                       }


                       $sql = "select  tT.tw_Rodzaj, tK.*, tT2.tw_Symbol from tw__Towar tT left join tw_Komplet as tK on tK.kpl_IdKomplet = tT.tw_Id left join tw__Towar as tT2 on tT2.tw_Id = tK.kpl_IdSkladnik where tT.tw_Symbol = '$code'";
                       $data = MSSql::getInstance()->query($sql);
//                       echo "<pre>";print_r($data);echo "</pre>";

							  $tw_Rodzaj = $data[0]["tw_Rodzaj"];
                       if($tw_Rodzaj == 8)
							  {
							    $jest_ok = true;
								 foreach ($data as $value) {
								   $check_code = $value['tw_Symbol'];
									$check_ilosc = round($value['kpl_Liczba']);
								   $symbole_towaru_w_komplecie[$check_code]['checked'] = 1;
									if (!isset($symbole_towaru_w_komplecie[$check_code]['ilosc']))
									{
									  $jest_ok = false;
									  $do_recznej_edycji[] = array("code" =>$code , "error" => "Za dużo elementów w komplecie w subiekcie");
									  $action = 'to_update';
									}

									if (($jest_ok) and ($symbole_towaru_w_komplecie[$check_code]['ilosc'] != $check_ilosc))
									{
									   $jest_ok = false;
										$do_recznej_edycji[] = array("code" =>$code , "error" => "Różne ilości w kompletach");
										$action = 'to_update';
									}
								 }

								 foreach ($symbole_towaru_w_komplecie as $value) {
								   if ($value['checked'] == 0 )
									{
									  $jest_ok = false;
									  $do_recznej_edycji[] = array("code" =>$code , "error" => "Brak elementów w komplecie w subiekcie");
									  $action = 'to_update';
									}
								 }
								 if ($jest_ok) $action = 'brak';

                       } else
							  {
							  	 $do_recznej_edycji[] = array("code" =>$code , "error" => "Pozycja nie jest kompletem w subiekcie 3");
								 $action = 'to_update';
								}
                    }

                    $i++;
                }
            } catch (\Exception  $e) {
                $e_message = strip_tags($e->getMessage() . ' Line:' . $e->getLine());
                $e_message = str_replace(array("\n", "\r"), '', $e_message);
                $this->errors_arr[] = array("id: " . $towar["code"], trim($e_message));
                $errors++;
            }
            $counter[$action]++;
        }
        return array($i, $counter, $errors, $do_recznej_edycji, $this->errors_arr);
    }

	public function getPriceCalculations(){

		  $pozycja = 0;
        foreach ($this->objDetail as $towar) {
            $code = $towar['code'];
            try {
                if ($code != '' && $this->subiektGt->TowaryManager->IstniejeWg($code, 2)) {
                    $this->productGt = $this->subiektGt->TowaryManager->WczytajTowarWg($code, 2);
						  $priceList = $this->productGt->Zakupy;
				  		  for ($i = 1, $size = $priceList->Liczba; $i<$size + 1; ++$i)
						  {
							  $data[$pozycja][$i]['code'] = $code;
							  $data[$pozycja][$i]['nazwa'] = $priceList->Element($i)->Nazwa;
							  $data[$pozycja][$i]['wartosc'] = (string)$priceList->Element($i)->Wartosc;
		 				  }
						  $pozycja++;
                }
            } catch (\Exception  $e) {
                $e_message = strip_tags($e->getMessage() . ' Line:' . $e->getLine());
                $e_message = str_replace(array("\n", "\r"), '', $e_message);
                $this->errors_arr[] = array("id: " . $towar["code"], trim($e_message));
                $errors++;
            }
        }
        return array($data, $pozycja, "errors_info"=>$this->errors_arr);
	}

	public function getAllPriceCalculations(){
		$magazyn = $this->objDetail['magazyn'];
		$pozycje = $this->getListAviByStore($magazyn);
		$errors = 0;
 	   $numer = 0;
      foreach ($pozycje as $towar) {
				$numer++;
            $code = $towar['code'];
            try {
                $this->productGt = $this->subiektGt->TowaryManager->WczytajTowarWg($code, 2);
//				    $priceList = $this->productGt->Zakupy;
				    $zakup = $this->getZakupPrice($towar['id']);
					 $data[$code]['code'] = $code;
					 $data[$code]['available'] = $towar['available'];
					 $data[$code]['nazwa'] = $towar['nazwa'];
					 $data[$code]['cena_manualna'] = $towar['cena_manualna'];
					 $data[$code]['producent'] = $towar['producent'];
					 $data[$code]['kategoria'] = $towar['kategoria'];
					 $data[$code]['grupa'] = $towar['grupa'];
//					 $data[$code]['cena'] = (string)$priceList->Element(2)->Wartosc;
					 $data[$code]['cena'] = $zakup['cena_zakup'];
            } catch (\Exception  $e) {
                $e_message = strip_tags($e->getMessage() . ' Line:' . $e->getLine());
                $e_message = str_replace(array("\n", "\r"), '', $e_message);
                $this->errors_arr[] = array("id: " . $towar["code"], trim($e_message));
                $errors++;
            }
        }
        return array($data, "pozycji" => $numer, "errors" => $errors);
	}


	public function getListByStore(){
		$sql = "SELECT tw_Symbol as code ,Rezerwacja as reservation,Dostepne as available, Stan as on_store,  st_MagId as id_store FROM vwTowar WHERE st_MagId = ".intval($this->id_store);
		$data = MSSql::getInstance()->query($sql);
		return $data;
	}

    public function getZakupPrice($towar_id){
        $sql = "select ISNULL(SUM(dm.mr_Cena*dm.mr_Pozostalo)/sum(dm.mr_Pozostalo),0) as cena_zakup from dok_MagRuch dm where  dm.mr_MagId > 0 and dm.mr_Pozostalo > 0 and dm.mr_TowId = ".intval($towar_id);
        $data = MSSql::getInstance()->query($sql);
        return $data[0];
    }

	public function getListAviByStore($magazyn){
		$sql = "SELECT tw_Id as id, tw_Symbol as code ,Rezerwacja as reservation,Dostepne as available, Stan as on_store,  st_MagId as id_store, tw_Nazwa as nazwa, tc_CenaNetto3 as cena_manualna, tw_Pole1 as producent, tw_Pole3 as kategoria, tw_IdGrupa as grupa FROM vwTowar WHERE st_MagId = ".intval($magazyn)." AND Dostepne > 0 and tw_IdGrupa < 6";
		$data = MSSql::getInstance()->query($sql);
		return $data;
	}

	public function getListAviByCode(){
		$codes = $this->objDetail[0]['codes'];
		$sql = "SELECT tw_Symbol as code ,Rezerwacja as reservation,Dostepne as available, Stan as on_store,  st_MagId as id_store, tw_Nazwa as nazwa, tc_CenaNetto3 as cena_manualna, tw_Pole1 as producent, tw_Pole3 as kategoria, tw_IdGrupa as grupa FROM vwTowar WHERE st_MagId = 3 and tw_Symbol in (".$codes.")";
		$data = MSSql::getInstance()->query($sql);
		return $data;
	}


    public function update()
    {
        return $this;
    }

    public function getGt()
    {
        return $this->productGt;
    }

    public function getGtObject()
    {
        return true;
    }


}

?>