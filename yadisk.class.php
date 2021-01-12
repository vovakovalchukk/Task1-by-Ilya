<?php 


class YaDisk {
	private $token;
	private $data;
	private $currentFilePath;
	private $currentDirPath;

	public function __construct($token, $data) {
		if(isset($token)) {
			$this->token = $token;
		} else {
			throw new Exception('Access token is required');
		}
		if(isset($data)) {
			if(is_array($data)) {
				$this->data = $data;
				// DB connection 
				$db = new mysqli('localhost', 'laravel.admin.panel.q', 'laravel.admin.panel.q', 'erips');
				//
				for ($i=0; $i < sizeof($data); $i++) { 
					$name = $data[$i]['name'];
					$yy = $data[$i]['year'];
					$mm = $data[$i]['month'];
					$dd = $data[$i]['day'];
					$path = __DIR__.'/AllErips/'.$name;
					$YDPath = '/'.$yy.'/'.$mm.'/'.$dd.'/'.$name;
					$DBDate = $dd.'/'.$mm.'/'.$yy;

					$this->CheckPath($yy, $mm, $dd);
					$uploadURL = $this->GetUploadURL($yy, $mm, $dd, $name);
					$this->UploadFile($path, $uploadURL);
					$db->query("INSERT INTO `allerips` SET `patch` = '".$YDPath."', `date` = '".$DBDate."'");
				}
				//
			} else {
				throw new Exception('Data type is not array');
			}
		} else {
			throw new Exception('Data array is required');
		}
	}

	private function CreatePath($yy, $mm, $dd) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Accept: application/json",
			"Content-Type: application/json",
			"Authorization: OAuth ".$this->token
		]);
		curl_setopt($ch, CURLOPT_PUT, 1);

		$path = '/'.$yy;
		curl_setopt($ch, CURLOPT_URL, 'https://cloud-api.yandex.net/v1/disk/resources?path='.urlencode($path));
		curl_exec($ch);

		$path = '/'.$yy.'/'.$mm;
		curl_setopt($ch, CURLOPT_URL, 'https://cloud-api.yandex.net/v1/disk/resources?path='.urlencode($path));
		curl_exec($ch);

		$path = '/'.$yy.'/'.$mm.'/'.$dd;
		curl_setopt($ch, CURLOPT_URL, 'https://cloud-api.yandex.net/v1/disk/resources?path='.urlencode($path));
		curl_exec($ch);
	
		curl_close($ch);
	}

	private function CheckPath($yy, $mm, $dd) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Accept: application/json",
			"Content-Type: application/json",
			"Authorization: OAuth ".$this->token
		]);

		$path = '/'.$yy.'/'.$mm.'/'.$dd;
		curl_setopt($ch, CURLOPT_URL, 'https://cloud-api.yandex.net/v1/disk/resources?path='.urlencode($path));
		$response = json_decode(curl_exec($ch));

		curl_close($ch);

		if(isset($response->error)) {
			$this->CreatePath($yy, $mm, $dd);
		} 
	}

	private function GetUploadURL($yy, $mm, $dd, $filename) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Accept: application/json",
			"Content-Type: application/json",
			"Authorization: OAuth ".$this->token
		]);

		$path = '/'.$yy.'/'.$mm.'/'.$dd.'/'.$filename;
		curl_setopt($ch, CURLOPT_URL, 'https://cloud-api.yandex.net/v1/disk/resources/upload?path='.urlencode($path).'&overwrite=true');
		$response = json_decode(curl_exec($ch));

		curl_close($ch);

		if(isset($response->href)) {
			return $response->href;
		} else {
			throw new Exception('Failed to retrieve upload URL');
		}
	}

	private function UploadFile($path, $URL) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Accept: application/json",
			"Content-Type: application/json",
			"Authorization: OAuth ".$this->token
		]);
		$fp = fopen($path, "rb");
		curl_setopt($ch, CURLOPT_PUT, 1);
		curl_setopt($ch, CURLOPT_INFILE, $fp);
		curl_setopt($ch, CURLOPT_INFILESIZE, filesize($path));
		curl_setopt($ch, CURLOPT_URL, $URL);

		curl_exec($ch);

		curl_close($ch);
		fclose($fp);
	}

}

class checkFiles{
    private $dir;
    private $filesToYD;
    private $dayLimit;

    function __construct($dayLimit, $dir = "AllErips") {
		if(isset($dayLimit)) {
			$this->dayLimit = $dayLimit;
		} else {
			throw new Exception('Day Limit value is required');
		}
        $this->dir = $dir;
        //$this->CheckDateFiles();
        $iterator = new DirectoryIterator("AllErips");
        while($iterator->valid()) {
            $file = $iterator->current();
            if($file != "." && $file != ".."){
                $explodeName = explode(" name", $file->getFilename());
                $currentDate = date("d.m.Y");
                $fileNameDate = date("d.m.Y", strtotime($explodeName[0]));
                if($currentDate - $fileNameDate > $this->dayLimit){
                    echo $iterator->key() . " => " . $file->getFilename() ."<br>";
                    preg_match('/(?P<day>\d+).(?P<month>\d+).(?P<year>\d+)/', $fileNameDate, $matches);
                    $this->filesToYD[] = [
                        "name"=>$file->getFilename(),
                        "year"=>$matches['year'],
                        "month"=>$matches['month'],
                        "day"=>$matches['day'],
                    ];
                }
            }
            $iterator->next();
        }
    }

    function GetFilesToYD() {
        if(!empty($this->filesToYD)){
            return $this->filesToYD;
        }
    }

    function CheckDateFiles() {

		/* КОД НИЖЕ РАБОТАЕТ БЫСТРЕЕ!!! */

        /*if($handle = opendir($this->dir)){
            while(false !== ($file = readdir($handle))) {
                if($file != "." && $file != ".."){
                $a = explode(" name", $file);
                $today = date("d.m.Y");
                $aDate = date("d.m.Y", strtotime($a[0]));
                if ($today - $aDate > $this->dayLimit){
                    //echo $file . " == " . $a[0] . " == " . $aDate .  "<br>";
                    preg_match('/(?P<day>\d+).(?P<month>\d+).(?P<year>\d+)/', $aDate, $matches);
                    $this->filesToYD[] = [
                        "name"=>$file,
                        "year"=>$matches['year'],
                        "month"=>$matches['month'],
                        "day"=>$matches['day'],
                    ];
                }
            }
            }
        }*/
    }
}


$token = 'AgAAAABOVTLjAAbP7vzqw20HCECcsQokSUy3A9Q';
$newTask1 = new checkFiles(7, "AllErips");
$filesToYD = $newTask1->GetFilesToYD();
$disk = new YaDisk($token, $filesToYD);

/*$array = [
	['name' => 'rand.png', 'year' => '2001', 'month' => '12', 'day' => '01'],
	['name' => 'rand.png', 'year' => '2002', 'month' => '12', 'day' => '01'],
	['name' => 'rand.png', 'year' => '2001', 'month' => '11', 'day' => '01']
];*/

?>