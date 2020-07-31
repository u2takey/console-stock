<?php
require('Stock.php');
require('color.php');
if ($argv[1] === "watch"){
    while (true){
        system('clear');
        print $White."\n\n";
        print $White."watch will refresh stocks in every 15s";
        print $White."\n\n";
        search_stock(array_slice($argv, 2));
        sleep(15);
    }
}else{
    search_stock(array_slice($argv, 1));
}

function search_stock($codes) {
    require('color.php');
    $ret = [];
    foreach($codes as $code){
        $sb = new SmartBox();
        $sb->setKeyWord($code);
        $sb->search();
        $a = $sb->results();
        foreach($a as $b) {
            $r1 = $b['title']." ".$b['subtitle'];
            $r2 = preg_split("/[\s]+/", $r1);
            // [上海]  长电科技  600584  42.69  +1.93%  sh600584（CDKJ）  高:43.58  低:41.61  收:41.88  开:41.61
    
            if(count($r2) == 10){
                $mask = " %-30s %-15s %-15s %-10s %-40s \n";
                $newarry = [$r2[0].$r2[1],$r2[2],$r2[3],$r2[4],$r2[6].','.$r2[7].','.$r2[8].','.$r2[9]];
                array_push($ret, $newarry);
                $price_color=$BGreen;
                if (substr( $r2[4], 0, 1 ) === "+"){
                    $price_color=$BRed;
                }
                if (ctype_digit($code) && $code !== $r2[2]){
                    continue;
                }
    
                printf($mask, $BBlue.$r2[0]." ".$r2[1],$BYellow.$r2[2],$price_color.$r2[3],$r2[4],$BPurple.$r2[6].','.$r2[7].','.$r2[8].','.$r2[9]);
            }
        }
    }
} 
?>