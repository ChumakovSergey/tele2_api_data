<?php
/**
 * Created by PhpStorm.
 * User: Чумаков
 * Date: 09.04.2019
 * Time: 10:10
 */
namespace TELE2;
class API
{
    private
        $curl,
        $cookie,
        $login,
        $password,
        $clientID;

    private function getData()
    {
        $page = curl_exec($this->curl);
        $obj = json_decode($page);
        for ($i = 0; $i < 3; $i++) {
            if ($obj->meta->status == "OK")
                return $obj->data;
        }
        echo "\nError in request: ".curl_getinfo($this->curl,CURLOPT_URL).". Response: " . $page;
    }

    public function __construct($login, $password)
    {
        $this->login = $login;
        $this->password = $password;
        $options = array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_POST => 0,
            CURLOPT_USERAGENT => "Mozilla/4.0 (Windows; U; Windows NT 5.0; En; rv:1.8.0.2) Gecko/20070306 Firefox/1.0.0.4",
            CURLOPT_COOKIESESSION => true,
            CURLOPT_COOKIEFILE => "tmp/cookie.txt",
            CURLOPT_COOKIEJAR => "tmp/cookie.txt",
            CURLOPT_VERBOSE => 1
        );
        $this->curl = curl_init();
        curl_setopt_array($this->curl, $options);
    }

    public function Auth($debug = false)
    {
        if ($this->clientID)
            return true;
        else {
            $security_params_template = "/value=\"([^\"]+)\" name=\"_csrf\"/s";
            curl_setopt($this->curl, CURLOPT_URL, "https://login.tele2.ru/ssotele2/wap/auth/");
            $page = curl_exec($this->curl);
            if (curl_getinfo($this->curl, CURLINFO_HTTP_CODE) != 200) {
                if ($debug)
                    print($page . "\n FUNCTION = Auth. REQUEST = 1. ANSWER = " . curl_getinfo($this->curl, CURLINFO_HTTP_CODE) . "\n");
                return false;
            }
            if (($page != "") && (preg_match($security_params_template, $page, $matches))) {
                curl_setopt($this->curl, CURLOPT_URL, "https://login.tele2.ru/ssotele2/wap/auth/submitLoginAndPassword");
                curl_setopt($this->curl, CURLOPT_POST, 1);
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query(array("pNumber" => $this->login, "password" => $this->password, "_csrf" => $matches[1], "authBy" => "BY_PASS", "rememberMe" => "true")));

                $page = curl_exec($this->curl);
                if (curl_getinfo($this->curl, CURLINFO_HTTP_CODE) != 200) {
                    if ($debug)
                        print($page . "\n\n FUNCTION = Auth. REQUEST = 2. ANSWER = " . curl_getinfo($this->curl, CURLINFO_HTTP_CODE) . "\n");
                    return false;
                } else {
                    curl_setopt($this->curl, CURLOPT_URL, "https://newlk.tele2.ru/subscribers?format=json");
                    $page = curl_exec($this->curl);
                    $obj = json_decode($page);
                    $this->clientID = $obj->b2bSessionData->parentClient->clientId;
                    return true;
                }
            }
        }
        return false;
    }

    public function getPhones($length = 0)
    {
        if ($this->Auth()) {
            $offset = 0;
            $limit = 20;
            $phone_array = array();
            do {
                curl_setopt($this->curl, CURLOPT_URL, "https://newlk.tele2.ru/api/b2b/clients/$this->clientID/subscribers?offset=$offset&limit=" . ($length - $offset > $limit || $length == 0 ? $limit : $length - $offset));
                $data = $this->getData();
                $offset += $limit;
                foreach ($data->pageData as $phone) {
                    array_push($phone_array, $phone);
                }
            } while ($data->total > $offset && ($length == 0 || $length > count($phone_array)));
            return $phone_array;
        } else return "Auth is failed";
    }

    public function getBalance()
    {
        if ($this->Auth()) {
            curl_setopt($this->curl, CURLOPT_URL, "https://newlk.tele2.ru/api/b2b/clients/$this->clientID/balance");
            $data = $this->getData();
            return $data->balance;//{amount: 150, currency: "RUB"}
        }
    }

    public function getMonthConsumption($phone, $monthIndex)
    {
        if ($this->Auth()) {
            curl_setopt($this->curl, CURLOPT_URL, "https://newlk.tele2.ru/api/b2b/clients/$this->clientID/subscribers/$phone/charges?month=$monthIndex");
            $data = $this->getData();
            return $data;
        }
    }

    public function getRatePlanName($phone)
    {
        if ($this->Auth()) {
            curl_setopt($this->curl, CURLOPT_URL, "https://newlk.tele2.ru/api/b2b/clients/$this->clientID/subscribers/$phone/rate");
            $data = $this->getData();
            return $data->ratePlanName;
        }
    }

    public function getQuotaAmountMax($phone)
    {
        if ($this->Auth()) {
            curl_setopt($this->curl, CURLOPT_URL, "https://newlk.tele2.ru/api/b2b/clients/$this->clientID/subscribers/$phone/quota");
            $data = $this->getData();
            return $data->quotaAmountMax === null ? 'null' : $data->quotaAmountMax;//{amount: 150, currency: "RUB"} or NULL
        }
    }

    public function getIccId($phone)
    {
        if ($this->Auth()) {
            curl_setopt($this->curl, CURLOPT_URL, "https://newlk.tele2.ru/api/b2b/clients/$this->clientID/subscribers/$phone/profile");
            $data = $this->getData();
            return $data->subscriberData->iccId;
        }
    }
}