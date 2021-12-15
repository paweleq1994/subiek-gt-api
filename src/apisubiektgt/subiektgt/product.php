<?php
namespace APISubiektGT\SubiektGT;
use COM;
use APISubiektGT\MSSql;
use APISubiektGT\Logger;
use APISubiektGT\SubiektGT\SubiektObj;
use APISubiektGT\SubiektGT;

class Product extends SubiektObj{

	protected $productGt = false;
	protected $rodzaj;
	protected $producentId = '';
	protected $ean = '';
	protected $code = '';
	protected $price;
	protected $wholesale_price = 0;
	protected $name;
	protected $name_for_devices;
	protected $description = '';
	protected $uwagi = '';
	protected $qty;
	protected $qty_stan;
	protected $qty_rezerwacja;
	protected $qty_dostepne;
	protected $supplier_code = '';
	protected $supplier_id = '';
	protected $vat = 23;
	protected $aktywny = true;

	protected $attribute;
	protected $id_store = 1;
	protected $products_qtys = array();
	protected $products_qtys_by_supplier = 0;
	protected $group_id = '';
	protected $off_prefix = 0;


	public function __construct($subiektGt,$productDetail = array()){
		parent::__construct($subiektGt, $productDetail);
		$this->excludeAttr(array('productGt','off_prefix','is_exists','objDetail'));
		if($this->code!='' &&  $subiektGt->TowaryManager->IstniejeWg($this->code,2)){
			$this->productGt = $subiektGt->TowaryManager->WczytajTowarWg($this->code,2);
			if (($this->aktywny == true) and ($this->aktywny != $this->productGt->Aktywny))
			{
			  $this->productGt->Aktywny = true;
			  $this->productGt->Zapisz();
			}
			$this->is_exists = true;
			$this->getGtObject();
		}
	}

	protected function setGtObject(){


		if(!empty($this->aktywny)){
		 	$this->productGt->Aktywny = $this->aktywny;
		}

		if(!empty($this->name)){
		 	$this->productGt->Nazwa = substr("{$this->name}",0,50);
		}

		//Opis
		if(!empty($this->description)){
			$this->productGt->Opis = $this->description;
		}

		//nazwa dla urzadzen
		if(!empty($this->name_for_devices)){
			$this->productGt->NazwaDlaUF = substr("{$this->name_for_devices}",0,50);
		}

		if(!empty($this->code)){
			$this->productGt->Symbol = substr(sprintf('%s',$this->code),0,20);
		}

		//cena detaliczna
		if($this->productGt->Ceny->Liczba>0){
			$this->productGt->Ceny->Element(1)->Brutto = floatval($this->price);
		}

		//cena hurtowa
		if($this->productGt->Ceny->Liczba>1 && $this->wholesale_price>0){
			$this->productGt->Ceny->Element(2)->Netto = floatval($this->wholesale_price);
		}

		//stawka vat
		if(!empty($this->vat)){
			$this->productGt->SprzedazVatId = $this->vat;
		}

		// uwagi
		if(!empty($this->uwagi)){
			$this->productGt->Uwagi = $this->uwagi;
		}

		//GrupaId
		if(!empty($this->group_id)){
			$this->productGt->GrupaId = intval($this->group_id);
		}

		//atrybut
		if(!empty($this->attribute)){
		  foreach ($this->attribute as $value)
		  {
		  		$this->productGt->Cechy->Dodaj($value);
		  }
		}

		if(!empty($this->pola_dodatkowe)){
		  $id = 1;
		  foreach ($this->pola_dodatkowe as $value)
		  {
		  	   $pole = "Pole".$id;
				$value = iconv( "UTF-8", "Windows-1250", $value);
		  		$this->productGt->{$pole} = $value;
				$id++;
		  }
		}

		//GrupaId
		if(!empty($this->JpkVat)){
			$this->productGt->OznaczenieJpkVat = intval($this->JpkVat);
		}

		if(!empty($this->supplier_code)){
			 $this->productGt->SymbolUDostawcy = substr(sprintf('%s',$this->supplier_code),0,20);
		}

		//podstawowy kod kreskowy
		$ean = substr(sprintf('%s',trim($this->ean)),0,20);
		if(!empty($ean)){
			$this->productGt->KodyKreskowe->Podstawowy = $ean;
 		}


//		com_print_typeinfo($this->productGt);
		return true;
	}

	public function getGtObject(){
		if(!$this->productGt){
			return false;
		}
		$this->gt_id = $this->productGt->Identyfikator;
		$this->name = $this->productGt->Nazwa;
		$this->description = $this->productGt->Opis;
		$this->code = $this->productGt->Symbol;
		$this->supplier_id = $this->productGt->DostawcaId;
		$this->vat = $this->productGt->SprzedazVatId;
		$this->supplier_code = $this->productGt->SymbolUDostawcy;
		$this->ean =$this->productGt->KodyKreskowe->Podstawowy;
		$this->rodzaj = $this->productGt->Rodzaj;
		$this->producentId = $this->productGt->ProducentId;
		$this->aktywny = $this->productGt->Aktywny;


		if($this->productGt->Ceny->Liczba>0){
			$prices = $this->productGt->Ceny->Element(1);
			$this->price = floatval($prices->Brutto);
		}

		if($this->productGt->Ceny->Liczba>1){
			$prices = $this->productGt->Ceny->Element(2);
			$this->wholesale_price = floatval($prices->Netto);
		}
		$qty = $this->getQty();
		$this->qty_stan = intval($qty['Stan']);
		$this->qty_rezerwacja = intval($qty['Rezerwacja']);
		$this->qty_dostepne = intval($qty['Dostepne']);

		return true;
	}

	public function getPriceCalculations(){
		if(!$this->productGt){
			return false;
		}
		$this->setGtObject();
		$this->productGt = $this->subiektGt->Towary->Wczytaj($this->code);

		Logger::getInstance()->log('api','Pobrano kalkulacje cen: '.$this->productGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);

		$priceList = $this->productGt->Zakupy;

		for ($i = 1, $size = $priceList->Liczba; $i<$size + 1; ++$i)
		{
			$data[$i]['nazwa'] = $priceList->Element($i)->Nazwa;
			$data[$i]['wartosc'] = (string)$priceList->Element($i)->Wartosc;
		}


		 return $data;
	}

	public function getListByStore(){
		$sql = "SELECT tw_Symbol as code ,Rezerwacja as reservation,Dostepne as available, Stan as on_store,  st_MagId as id_store FROM vwTowar WHERE st_MagId = ".intval($this->id_store);
		$data = MSSql::getInstance()->query($sql);
		return $data;
	}

	public function getListAviByStore(){
		$sql = "SELECT tw_Symbol as code ,Rezerwacja as reservation,Dostepne as available, Stan as on_store,  st_MagId as id_store FROM vwTowar WHERE st_MagId = ".intval($this->id_store).' AND Dostepne > 0';
		$data = MSSql::getInstance()->query($sql);
		return $data;
	}

	public function getQtysByCode(){
		$qtys = array();
		foreach($this->products_qtys as $pq){
		$code = $pq['code'];
		$id_store = isset($pq['id_store'])?intval($pq['id_store']):0;
		$sql = 'SELECT tw_Id as id ,tw_Symbol as code, Rezerwacja as reservation , Dostepne as available, Stan as on_store, Stan-Rezerwacja as on_store_available   FROM vwTowar LEFT JOIN
			tw_KodKreskowy ON kk_IdTowar = tw_Id
			WHERE st_MagId = '.$id_store.' AND tw_Symbol = \''.$code.'\'';

			$data = MSSql::getInstance()->query($sql);
			if(!isset($data[0])){
				$qtys[$code] = 'not found';
				continue;
			}
		 	$qtys[$code]['id'] = $data[0]['id'];
		 	$qtys[$code]['code'] = $data[0]['code'];
		 	$qtys[$code]['reservation'] = intval($data[0]['reservation']);
		 	$qtys[$code]['available'] = intval($data[0]['available']);
		 	$qtys[$code]['on_store'] = intval($data[0]['on_store']);
		 	$qtys[$code]['on_store_available'] = intval($data[0]['on_store_available']);

		}
		return $qtys;
	}


	public function getQtysBySupplier(){
		$sql = "SELECT tw_Id as id ,tw_Symbol as code, Rezerwacja as reservation , Dostepne as available, Stan as on_store, tc_CenaNetto1 as price1, tc_CenaNetto2 as price2, tc_CenaNetto3 as price3, tc_CenaNetto4 as price4, tc_CenaNetto5 as price5, tw_Nazwa as name  FROM vwTowar LEFT JOIN
			tw_KodKreskowy ON kk_IdTowar = tw_Id
			WHERE tw_IdPodstDostawca = {$this->products_qtys_by_supplier} and Dostepne > 0 AND st_MagId = {$this->id_store}";

			$data = MSSql::getInstance()->query($sql);


		return $data;
	}


	protected function getQty(){
		$sql = "SELECT TOP 1 Rezerwacja,Dostepne,Stan  FROM vwTowar WHERE tw_Id = {$this->gt_id} AND st_MagId = ".intval($this->id_store);
		$data = MSSql::getInstance()->query($sql);
		return $data[0];
	}

	public function addTowar(){
		$action = 'insert_towar';
		if($this->productGt){
			$action = 'update_towar';
			$this->readData($this->objDetail);
		} else $this->productGt = $this->subiektGt->TowaryManager->DodajTowar();
		$this->setGtObject();
		echo $this->code;
	 	$this->productGt->Zapisz();
		Logger::getInstance()->log('api','Utworzono produkt: '.$this->productGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
		return array('action' => $action, 'gt_id'=>$this->productGt->Identyfikator);
	}

	public function addUsluga(){
		$action = 'insert_usluga';
		if($this->productGt){
			$action = 'update_usluga';
			$this->readData($this->objDetail);
		} else $this->productGt = $this->subiektGt->TowaryManager->DodajUsluge();
		$this->setGtObject();
		$this->productGt->Zapisz();
		Logger::getInstance()->log('api','Utworzono usluge: '.$this->productGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
		return array('action' => $action, 'gt_id'=>$this->productGt->Identyfikator);
	}

	public function addKomplet(){
		$action = 'insert_komplet';
		if($this->productGt){
			$action = 'update_komplet';
			$this->readData($this->objDetail);
		} else $this->productGt = $this->subiektGt->TowaryManager->DodajKomplet();
		$this->setGtObject();
		$this->productGt->Zapisz();
		Logger::getInstance()->log('api','Utworzono komplet: '.$this->productGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
		return array('action' => $action, 'gt_id'=>$this->productGt->Identyfikator);
	}


	public function setProductSupplierCode($supplier_code){
		if(!$this->productGt){
			return false;
		}
		$this->productGt->SymbolUDostawcy = substr(sprintf('%s',$supplier_code),0,20);
		$this->productGt->Zapisz();
		Logger::getInstance()->log('api','Zaktualizowano kod dostawcy produktu: '.$supplier_code,__CLASS__.'->'.__FUNCTION__,__LINE__);
		return true;
	}

	public function update(){
		if(!$this->productGt){
			return false;
		}
		$this->readData($this->objDetail);
		$this->setGtObject();
		$this->productGt->Zapisz();
		Logger::getInstance()->log('api','Zaktualizowano produkt: '.$this->productGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
		return $this;
	}

	public function getGt(){
		return $this->productGt;
	}
}
?>