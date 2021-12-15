<?php
namespace APISubiektGT\SubiektGT;
use COM;
use Exception;
use APISubiektGT\Logger;
use APISubiektGT\MSSql;
use APISubiektGT\Helper;
use APISubiektGT\SubiektGT\SubiektObj;
use APISubiektGT\SubiektGT\Product;
use APISubiektGT\SubiektGT\Customer;

class Order extends SubiektObj {
    protected $orderGt;
    protected $products = false;
    protected $reference;
    protected $comments;
    protected $customer = false;
    protected $odbiorca = false;
    protected $reservation = true;
    protected $order_ref = '';
    protected $selling_doc = '';
    protected $amount = 0;
    protected $paid_amount = 0;
    protected $state = -1;
    protected $date_of_delivery = '';
    protected $payment_comments = '';
    protected $pay_type = 'transfer';
    protected $payment_deadline;
    protected $create_product_if_not_exists = false;
    protected $orderDetail= array();
    protected $order_processing = false;
    protected $id_flag = 0;
    protected $flag_txt = '';
    protected $ot_shipping = '';



    public function __construct($subiektGt,$orderDetail = array()){
        parent::__construct($subiektGt, $orderDetail);
        $this->excludeAttr(array('orderGt','orderDetail','pay_type','create_product_if_not_exists'));


        if($this->order_ref !='' && $subiektGt->SuDokumentyManager->Istnieje($this->order_ref)){
            $this->orderGt = $subiektGt->SuDokumentyManager->Wczytaj($this->order_ref);
            $this->getGtObject();
            $this->is_exists = true;
        }
        $this->orderDetail = $orderDetail;
    }

    protected function addPosition($product){
        $position = false;
        $p = new Product($this->subiektGt,$product);
        if(!$p->isExists()){
            return false;
        }
        $p_data = $p->get();
        if(isset($product['supplier_code']) && strlen($product['supplier_code'])>0){
            $p->setProductSupplierCode($product['supplier_code']);
        }
        //var_dump($p_data);
        $code = sprintf('%s',$p_data['code']);

        $position = $this->orderGt->Pozycje->Dodaj($code);
        $position->IloscJm = intval($product['qty']);
        $position->WartoscBruttoPoRabacie  = floatval($product['price']) * intval($product['qty']);
        if(floatval($product['price_before_discount'])>0){
            $position->WartoscBruttoPrzedRabatem = floatval($product['price_before_discount']) * intval($product['qty']);
        }
        Logger::getInstance()->log('api','Dodaje pozycje o kodzie: '.$code ,__CLASS__.'->'.__FUNCTION__,__LINE__);
        return $position;
    }

    protected function setGtObject(){
        $this->orderGt->Tytul = $this->reference;
        $uwagi = !empty($this->comments) ? '; Uwagi: ' . $this->comments : $this->comments;
        $this->orderGt->Uwagi  = 'Kurier: ' . rtrim($this->ot_shipping, ':') . $uwagi;
        $this->orderGt->Rezerwacja = $this->reservation;
        $this->orderGt->NumerOryginalny = $this->reference;
        switch($this->pay_type){
            case 'transfer' : $this->orderGt->PlatnoscPrzelewKwota = floatval($this->amount); break;
            case 'cart' : $this->orderGt->PlatnoscKartaKwota = floatval($this->amount); $this->orderGt->PlatnoscKartaId = 3; break;
            case 'money' : $this->orderGt->PlatnoscGotowkaKwota = floatval($this->amount); break;
            case 'credit' : $this->orderGt->PlatnoscKredytKwota = floatval($this->amount); $this->orderGt->PlatnoscKredytTermin = date('Y-m-d', strtotime("+".intval($this->payment_deadline)." day")); break;
            case 'cash_on_delivery' : $this->orderGt->PlatnoscRatyKwota = floatval($this->amount);  break;
            default:
                $this->orderGt->PlatnoscPrzelewKwota = floatval($this->amount);
                break;
        }


    }

    public function getPdf(){
        $temp_dir = sys_get_temp_dir();
        if($this->is_exists){
            $file_name = $temp_dir.'/'.$this->gt_id.'.pdf';
            if (strpos($this->order_ref, 'FS') === 0) {
                $id_wzorca = "1000036";//fs
            } elseif (strpos($this->order_ref, 'PA') === 0){
                $id_wzorca = "1000038";//pai
            } else {
                die("order_ref nie zaczyna się na FS lub PA");
            }
            $this->orderGt->DrukujDoPlikuWgWzorca($id_wzorca,$file_name,0);
            $pdf_file = file_get_contents($file_name);
            Logger::getInstance()->log('api','Wygenerowano pdf dokumentu: '.$this->order_ref ,__CLASS__.'->'.__FUNCTION__,__LINE__);
            return array('encoding'=>'base64','order_ref'=>$this->order_ref ,'pdf_file'=>base64_encode($pdf_file));
        }
        return false;
    }

    public function checkSaleDoc(){
        if(!$this->is_exists){
            return array(
                'order_ref' => $this->order_ref,
                'doc_state' => 'warning',
                'doc_state_code' => 1,
                'message' => 'Nie odnaleziono dokumentu',
                'doc_ref' => false
            );
        }

        if($this->customer['is_company'] == true){
            $selling_doc = $this->subiektGt->SuDokumentyManager->DodajFS();
        }else{
            $selling_doc = $this->subiektGt->SuDokumentyManager->DodajPAi();
        }
        if($this->orderGt->WartoscBrutto == 0){
            throw new Exception('Nie można utworzyć dokumentu sprzedaży. 0 wartość dokumentu.');
        }

        try{
            $selling_doc->NaPodstawie(intval($this->gt_id));
        }catch(Exception $e){
            throw new Exception('Nie można utworzyć dokumentu sprzedaży. Dokument: '.$this->order_ref.'. '.$this->toUtf8($e->getMessage()));
        }
        try{
            $selling_doc->ZapiszSymulacja();
        }catch(Exception $e){
            if($selling_doc->PozycjeBrakujace->Liczba()>0){
                $braki =  $selling_doc->PozycjeBrakujace;
                foreach ($braki as $brak){
                    echo "Pozycja na zamówieniu: " . $brak->Lp . "<br>";
                    echo "Nazwa towaru: " . iconv( "Windows-1250", "UTF-8", $brak->TowarNazwa) . "<br>";
                    echo "Wybrano: " . $brak->IloscJm . "<br>";
                    echo "Brakuje: " . $brak->Brak . "<br>";
                    echo "Na magazynie: " . $brak->MagazynStan . "<br>";
                    echo "Zarezerwowane: " . $brak->MagazynRezerwacja . "<br><br>";
                }
                return array(
                    'doc_ref' => $selling_doc->NumerPelny,
                    'doc_state' => 'warning',
                    'doc_state_code' => 2,
                    'message' => 'Nie można utworzyć dokumentu sprzedaży. Brakuje produktów na magazynie.',

                );
            }else{
                throw new Exception('Nie można utworzyć dokumentu sprzedaży. Dokument: '.$this->order_ref.'. '.$this->toUtf8($e->getMessage()));
            }
        }
        return array( 'doc_state' => 'można fiskalizować', 'order_ref' => $this->order_ref );
    }





    public function makeSaleDoc(){
        if(!$this->is_exists){
            return array(
                'order_ref' => $this->order_ref,
                'doc_state' => 'warning',
                'doc_state_code' => 1,
                'message' => 'Nie odnaleziono dokumentu',
                'doc_ref' => false
            );
        }

        if($this->customer['is_company'] == true){
            $selling_doc = $this->subiektGt->SuDokumentyManager->DodajFS();
            $drukarka_fiskalna = false;
        }else{
            $selling_doc = $this->subiektGt->SuDokumentyManager->DodajPAi();
            $drukarka_fiskalna = true;
        }
        if($this->orderGt->WartoscBrutto == 0){
            throw new Exception('Nie można utworzyć dokumentu sprzedaży. 0 wartość dokumentu.');
        }

        try{
            $selling_doc->NaPodstawie(intval($this->gt_id));
        }catch(Exception $e){
            throw new Exception('Nie można utworzyć dokumentu sprzedaży. Dokument: '.$this->order_ref.'. '.$this->toUtf8($e->getMessage()));
        }
        try{
            $selling_doc->ZapiszSymulacja();
        }catch(Exception $e){
            if($selling_doc->PozycjeBrakujace->Liczba()>0){
                return array(
                    'doc_ref' => $selling_doc->NumerPelny,
                    'doc_state' => 'warning',
                    'doc_state_code' => 2,
                    'message' => 'Nie można utworzyć dokumentu sprzedaży. Brakuje produktów na magazynie.',
                );
            }else{
                throw new Exception('Nie można utworzyć dokumentu sprzedaży. Dokument: '.$this->order_ref.'. '.$this->toUtf8($e->getMessage()));
            }
        }
        if($this->customer['is_company']== false){
            $selling_doc->RejestrujNaUF = true;
        }
        $selling_doc->Podtytul = trim($this->orderGt->Tytul);//.'/'.$this->orderGt->order_ref;
        $selling_doc->Wystawil = Helper::toWin($this->cfg->getIdPerson());
        $selling_doc->LiczonyOdCenBrutto = true;


//		print_r($this);

        $selling_doc->Zapisz();

        //w tym miejscu sprawdz polaczenie z drukarka fiskalna
        if ($drukarka_fiskalna == true){
            $selling_doc->RejestrujNaUF = true;
            $selling_doc->DrukarkaFiskalnaId = 12;
        }


        if($this->custom_print_settings === true){
            $oUstWyd = $selling_doc->PodajUstawieniaDrukowania(Null);//null to domyslny wzorzec wydruku
            $oUstWyd->DrukarkaDomyslSysOp = false;
            $oUstWyd->Drukarka = $this->printer_name;
            $oUstWyd->IloscKopii = $this->number_of_copies;
            $selling_doc->DrukujWgUstawien($oUstWyd);
        } else {
            $selling_doc->Drukuj();
        }


        Logger::getInstance()->log('api','Utworzono dokument sprzedaży: '.$selling_doc->NumerPelny,__CLASS__.'->'.__FUNCTION__,__LINE__);
        $response =  array(
            'doc_ref' => $selling_doc->NumerPelny,
            'doc_amount' => $this->getOrderAmountById($selling_doc->Identyfikator),
            'doc_state' => 'ok',
            'doc_state_code' => 0,
            'order_ref' => $this->order_ref,
            'status_fiskalny' => $selling_doc->StatusFiskalny

        );

        if(isset($this->pdf_request)){
            $response['doc_pdf'] = $this->getPdfInBase64($selling_doc);
        }

        return $response;
    }

    protected function getGtObject(){
        if(!$this->orderGt){
            return false;
        }
        $this->gt_id = $this->orderGt->Identyfikator;
        $o = $this->getOrderById($this->gt_id);

        $this->reference =  $o['dok_NrPelnyOryg'];
        $this->doc_type = $this->doc_types[$this->orderGt->Typ];
        $this->selling_doc = $o['pow_NrPelny'];
        $this->comments = $o['dok_Uwagi'];
        $this->order_ref = $o['dok_NrPelny'];
        $this->reservation = $o['statusrez'];
        $this->state = $o['dok_Status'];
        $this->amount = $o['dok_WartBrutto'];
        $this->date_of_delivery = $o['dok_TerminRealizacji'];
        $this->order_processing = $o['ss_PrzetworzonoZKwZD'];
        $this->id_flag = $o['flg_Id'];
        $this->flag_txt = $o['flg_Text'];

        $customer = Customer::getCustomerById($this->orderGt->KontrahentId);
        $this->customer = $customer;

        $positions = array();
        for($i=1; $i<=$this->orderGt->Pozycje->Liczba(); $i++){
            $positions[$this->orderGt->Pozycje->Element($i)->Id]['name'] = $this->orderGt->Pozycje->Element($i)->TowarNazwa;
            $positions[$this->orderGt->Pozycje->Element($i)->Id]['code'] = $this->orderGt->Pozycje->Element($i)->TowarSymbol;
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

    protected function getOrderById($id){
        $sql = "SELECT * FROM vwDok4ZamGrid  as d
				LEFT JOIN fl_Wartosc as fw ON (fw.flw_IdObiektu = d.dok_Id)
				LEFT JOIN fl__Flagi as f ON (f.flg_Id = fw.flw_IdFlagi)
				WHERE dok_Id = {$id}
		";
        $data = MSSql::getInstance()->query($sql);
        return $data[0];
    }


    protected function getOrderAmountById($id){
        $sql = "SELECT dok_WartBrutto FROM vwDok4ZamGrid WHERE dok_Id = {$id}";
        $data = MSSql::getInstance()->query($sql);
        if(!is_array($data)){
            return false;
        }
        return $data[0]['dok_WartBrutto'];
    }

    protected function getPositionsByOrderId($id){
        $sql = "SELECT * FROM dok_Pozycja
			   WHERE ob_DokHanId = {$id}";
        $data = MSSql::getInstance()->query($sql);
        return $data;
    }



    public function getState(){
        return array('order_ref'=>$this->order_ref,
            'is_exists' => $this->is_exists,
            'state' => $this->state,
            'order_processing' => $this->order_processing,
            'id_flag'	 	=> $this->id_flag,
            'flag_txt'		=> $this->flag_txt,
            'amount' => $this->amount,
            'sell_doc' =>$this->selling_doc
        );
    }

	public function getFakturaByOrderId(){
		$id = $this->orderDetail[0]['order_id'];
        $sql = "SELECT dok_NrPelny FROM vwDok4ZamGrid
			WHERE dok_DoDokNrPelny = '{$id}'";
        $data = MSSql::getInstance()->query($sql);
        return $data[0];
    }


    public function add(){
        $this->customer = isset($this->orderDetail['customer'])?$this->orderDetail['customer']:false;
        $this->odbiorca = isset($this->orderDetail['odbiorca'])?$this->orderDetail['odbiorca']:false;

        if(!$this->customer){
            throw new Exception('Brak danych "customer" dla zamówienia!',1);
        }
        if(!$this->products){
            throw new Exception('Brak danych "products" dla zamówienia!',1);
        }

        $this->orderGt = $this->subiektGt->SuDokumentyManager->DodajZK();


        $customer = new Customer($this->subiektGt,$this->customer);
        if(!$customer->isExists()){
            $customer->add();
        } else {
            $customer->update();
        }

        $cust_data = $customer->get();
        $this->orderGt->KontrahentId = intval($cust_data['gt_id']);


        if($this->odbiorca){
            $odbiorca = new Customer($this->subiektGt,$this->odbiorca);
            if(!$odbiorca->isExists()){
                $odbiorca->add();
            } else {
                $odbiorca->update();
            }
            $cust_data = $odbiorca->get();
            $this->orderGt->OdbiorcaId = intval($cust_data['gt_id']);
        }

		  $this->orderGt->LiczonyOdCenBrutto = true;

        foreach($this->products as $p){
            $add_postition = false;
            if(!($add_postition = $this->addPosition($p))
                && $this->create_product_if_not_exists == false){
                throw new Exception('Nie odnaleziono towaru o podanym kodzie: '.$p['code'],1);
            }
            if(!$add_postition && $this->create_product_if_not_exists == true){
                $p_obj = new Product($this->subiektGt,$p);
                $p_obj->addTowar();
                $this->addPosition($p);
            }
        }

        $this->orderGt->Przelicz();
        $this->amount = $this->orderGt->WartoscBrutto;
        $this->orderGt->Wystawil = Helper::toWin($this->cfg->getIdPerson());
        $this->setGtObject();
        $this->orderGt->Zapisz();
        Logger::getInstance()->log('api','Utworzono zamówienie od klienta: '.$this->orderGt->NumerPelny,__CLASS__.'->'.__FUNCTION__,__LINE__);
        return array(
            'order_ref' => $this->orderGt->NumerPelny,
            'order_amount' => $this->getOrderAmountById($this->orderGt->Identyfikator)
        );
    }

    public function update(){
        return true;
    }

    public function getGt(){
        return $this->orderGt;
    }

    public function setFlag(){
        if(!$this->is_exists){
            return false;
        }
        $this->subiektGt->UstawFlageWlasna($this->id_flag,$this->orderGt->Identyfikator,$this->flag_txt,"");
        return array('order_ref'=>$this->order_ref,'id_flag',$this->id_flag);
    }

    public function delete(){
        if(!$this->orderGt){
            return false;
        }

        $this->orderGt->Usun(false);
        return array('order_ref'=>$this->order_ref);
    }

    //get pdf file in base64
    protected function getPdfInBase64($gtObject){
        $temp_dir = sys_get_temp_dir();
        $file_name = $temp_dir.'/'.$gtObject->Identyfikator.'.pdf';
        $gtObject->DrukujDoPliku($file_name,0);
        $pdf_file = file_get_contents($file_name);
        unlink($file_name);
        return base64_encode($pdf_file);
    }

}
?>