<?php
namespace APISubiektGT\SubiektGT;

use COM;
use APISubiektGT\Logger;
use APISubiektGT\MSSql;
use APISubiektGT\SubiektGT\SubiektObj;
use Exception;


class Customer extends SubiektObj{
    protected $customerGt = false;
    protected $ref_id = false;
    protected $firstname;
    protected $lastname;
    protected $post_code;
    protected $city;
    protected $tax_id = '';
    protected $company_name = '';
    protected $address;
    protected $address_no = '';
    protected $phone = false;
    protected $is_company = false;
	 protected $Pole1;



    public function __construct($subiektGt,$customerDetail = array()){
        parent::__construct($subiektGt, $customerDetail);
        $this->excludeAttr('customerGt');


        // NIP-e spacji "- "
  //      $this->tax_id = preg_replace('/([ ])/','', $this->tax_id);

        //Wyszukanie po symbolu
        if($this->ref_id && $subiektGt->Kontrahenci->Istnieje($this->ref_id)){
            $this->customerGt = $subiektGt->Kontrahenci->Wczytaj($this->ref_id);
            $this->getGtObject();
            $this->is_exists = true;
        }

        //Wyszukanie po wprowadzonym NIP-e
//        if(!$this->customerGt && $this->is_company && $subiektGt->Kontrahenci->Istnieje($this->tax_id)){
//            $this->customerGt = $subiektGt->Kontrahenci->Wczytaj($this->tax_id);
//            $this->getGtObject();
//            $this->is_exists = true;
//        }

        //Wyszukanie po NIP-e wycięcie znaków "-"
//        $this->tax_id = preg_replace('/([\- ])/','', $this->tax_id);

//        if(!$this->customerGt && $this->is_company && $this->tax_id!=''){
//            if( $subiektGt->Kontrahenci->Istnieje($this->tax_id)){
//                $this->customerGt = $subiektGt->Kontrahenci->Wczytaj($this->tax_id);
//                $this->getGtObject();
//                $this->is_exists = true;
//            }
//        }
    }

    protected function setGtObject(){
        $this->customerGt->Symbol = substr($this->ref_id,0,20);

        if($this->is_company && strlen($this->tax_id)>=10){
            if(strlen($this->company_name)==0){
                throw new Exception('Nie można utworzyć klienta brak jego nazwy!');
            }

        		$this->tax_id = preg_replace('/([\- ])/','', $this->tax_id);
            $this->customerGt->NazwaPelna = $this->company_name;
            $this->customerGt->Nazwa = mb_substr($this->company_name,0,40);
            $this->customerGt->Osoba = 0;
            $this->customerGt->NIP =  substr(sprintf('%s',$this->tax_id),0,17);
				$this->customerGt->PowielNIPBezUI = true;
//            $this->customerGt->Symbol = $this->customerGt->NIP;
				$this->customerGt->GrupaId = 2;
				$this->customerGt->Typ = 2;


        }else{
            $this->customerGt->Osoba = 1;
            if (empty($this->lastname)){
                throw new Exception('Nie można utworzyć klienta. Brak jego nazwiska!');
            }
            $this->customerGt->OsobaNazwisko = substr($this->lastname,0,50);
            $this->customerGt->OsobaImie = substr($this->firstname,0,20);
            $this->customerGt->NazwaPelna = $this->firstname.' '.$this->lastname;
				$this->customerGt->GrupaId = 3;
				$this->customerGt->Typ = 2;
        }
        $this->customerGt->Pole1 = $this->Pole1;

        $this->customerGt->Miejscowosc = $this->city;
        $this->customerGt->KodPocztowy = substr($this->post_code,0,6);
        $this->customerGt->Ulica = substr($this->address,0,60);

        //odznacza GTU_06 - Urządzenia elektroniczne oraz części i materiały do nich
        $this->customerGt->UstawOznaczenieJpkVat(19, false);


//        $this->customerGt->NrDomu = substr($this->address_no,0,10);
/*
        if($this->phone){
            if($this->customerGt->Telefony->Liczba==0){
                $phoneGt = $this->customerGt->Telefony->Dodaj($this->phone);
            }else{
                $phoneGt = $this->customerGt->Telefony->Element(1);

            }
            $phoneGt->Nazwa = 'Primary';
            $phoneGt->Numer = $this->phone;
            $phoneGt->Typ = 3;
        }
*/
        return true;
    }

    protected function getGtObject(){
        $this->is_company = !$this->customerGt->Osoba;
        $this->gt_id = $this->customerGt->Identyfikator;
        $this->ref_id = $this->customerGt->Symbol;
        $this->company_name = $this->customerGt->NazwaPelna;
        $this->tax_id = $this->customerGt->NIP;
        $this->firstname = $this->customerGt->OsobaImie;
        $this->lastname = $this->customerGt->OsobaNazwisko;
        $this->Pole1 = $this->customerGt->Pole1;
        $this->city = $this->customerGt->Miejscowosc;
        $this->post_code = $this->customerGt->KodPocztowy;
        $this->address = $this->customerGt->Ulica;
        $this->address_no =$this->customerGt->NrDomu;

        if($this->customerGt->Telefony->Liczba>0){
            $phoneGt = $this->customerGt->Telefony->Element(1);
            $this->phone = $phoneGt->Numer;
        }
        return true;
    }

    static public function getCustomerById($id){
        $sql = "SELECT * FROM vwKlienci WHERE kh_Id = {$id}";
        $data = MSSql::getInstance()->query($sql);
        if(!isset($data[0])){
            return false;
        }
        $data = $data[0];
        $ret_data  = array(
            'ref_id' => $data['kh_Symbol'],
            'company_name' => $data['Firma'],
            'tax_id' => $data['adr_NIP'],
            'fullname' => $data['adr_NazwaPelna'],
            'Pole1' => $data['kh_Pole1'],
            'city' => $data['adr_Miejscowosc'],
            'post_code' => $data['adr_Kod'],
            'address' => $data['adr_Adres'],
            'phone' => $data['adr_Telefon'],
            'is_company' => $data['kh_Typ']==2?false:true,
        );
        return $ret_data;
    }

    public function add(){
        if ($this->is_exists){
            $this->readData($this->objDetail);
            $this->setGtObject();
            $this->customerGt->Zapisz();
            Logger::getInstance()->log('api','Zaktualizowano klienta od klienta: '.$this->customerGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
            return array('gt_id'=>$this->customerGt->Identyfikator, "symbol" => $this->customerGt->Symbol, "info"=>"Użytkownik o numerze ". $this->customerGt->Symbol ." już istniał w bazie. Dane zostały nadpisane");
        } else {
            $this->customerGt = $this->subiektGt->Kontrahenci->Dodaj();
            $this->setGtObject();
            $this->customerGt->Zapisz();
            Logger::getInstance()->log('api','Utworzono klienta od klienta: '.$this->customerGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
            $this->gt_id = $this->customerGt->Identyfikator;
            return array('gt_id'=>$this->customerGt->Identyfikator, "symbol" => $this->customerGt->Symbol,"info" => "Utworzono klienta");
        }
    }

    public function update() {
        if(!$this->customerGt){
            $this->customerGt = $this->subiektGt->Kontrahenci->Dodaj();
            $this->setGtObject();
            $this->customerGt->Zapisz();
            Logger::getInstance()->log('api','Utworzono klienta od klienta: '.$this->customerGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
            $this->gt_id = $this->customerGt->Identyfikator;
            return array('gt_id'=>$this->customerGt->Identyfikator, "symbol" => $this->customerGt->Symbol, "info" => "Nie zaktualizowano ponieważ klienta nie było w bazie. Klient został utworzony automatycznie.");
        } else {
            $this->readData($this->objDetail);
            $this->setGtObject();
            $this->customerGt->Zapisz();
            Logger::getInstance()->log('api','Zaktualizowano klienta od klienta: '.$this->customerGt->Symbol,__CLASS__.'->'.__FUNCTION__,__LINE__);
            return array('gt_id'=>$this->customerGt->Identyfikator, "symbol" => $this->customerGt->Symbol, "info" => "Zaktualizowano pomyślnie");
        }
    }

    public function getGt(){
        return $this->customerGt;
    }

}
?>