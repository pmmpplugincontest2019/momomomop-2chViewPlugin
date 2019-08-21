<?php

namespace chView;

use pocketmine\Server;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\item\Item;
use pocketmine\item\ItemIds;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;

class Start extends PluginBase implements Listener {

    public $status = [];

    public $dats = [];
    public $threads = [];

    public $data = [];

	public function onEnable() : void{
        $this->getServer()->getPluginmanager()->registerEvents( $this, $this );
    }

    public function onJoin(PlayerJoinEvent $ev){

        $p = $ev->getPlayer();

        $p->getInventory()->clearAll();

        $p->getInventory()->addItem(Item::get(ItemIds::WOODEN_PICKAXE,0,1));
        $p->getInventory()->addItem(Item::get(ItemIds::STONE_PICKAXE,0,1));
        $p->getInventory()->addItem(Item::get(ItemIds::IRON_PICKAXE,0,1));
        $p->getInventory()->addItem(Item::get(ItemIds::GOLDEN_PICKAXE,0,1));
        $p->getInventory()->addItem(Item::get(ItemIds::DIAMOND_PICKAXE,0,1));

        $p->sendMessage(
        "アイテムを配布しました\n".
        "木 : 前へ\n".
        "石 : 次へ\n".
        "鉄 : スレッドの選択\n".
        "金 : スレッドを抜ける\n".
        "ダイヤ : スレッド一覧更新/レス一覧更新\n"
        );
        
        global $status;

        $status[$p->getName()] = ["status"=>"none"];
    }

    public function onTap(PlayerInteractEvent $ev){
        $p = $ev->getPlayer();
        $name = $p->getName();
        $id = $ev->getItem()->getId();

        global $status,$dats,$threads,$data;

        switch($id){
            case ItemIds::WOODEN_PICKAXE:
                //前へ
                switch($status[$name]["status"]){
                    case "list":
                        //shift -1
                        $status[$name]["show"] = $status[$name]["show"] == 0 ? count($threads[$name])-1 : $status[$name]["show"]-1;
                        //表示
                        $p->sendMessage("§3thread §7>>> §f".$threads[$name][$status[$name]["show"]]);
                        break;
                    case "thread":
                        //shift -1
                        $status[$name]["res"] = $status[$name]["res"] == 0 ? count($data[$status[$name]["dat"]][1])-1 : $status[$name]["res"]-1;
                        //表示
                        $p->sendMessage($this->getResponceString($status[$name]["dat"],$status[$name]["res"]));
                        break;
                }
                break;
            case ItemIds::STONE_PICKAXE:
                //次へ
                switch($status[$name]["status"]){
                    case "list":
                        //shift +1
                        $status[$name]["show"] = $status[$name]["show"] == count($threads[$name])-1 ? 0 : $status[$name]["show"]+1;
                        //表示
                        $p->sendMessage("§3thread §7>>> §f".$threads[$name][$status[$name]["show"]]);
                        break;
                    case "thread":
                        //shift +1
                        $status[$name]["res"] = $status[$name]["res"] == count($data[$status[$name]["dat"]][1])-1 ? 0 : $status[$name]["res"]+1;
                        //表示
                        $p->sendMessage($this->getResponceString($status[$name]["dat"],$status[$name]["res"]));
                        break;
                }
                break;
            case ItemIds::IRON_PICKAXE:
                //選択
                if($status[$name]["status"]=="list"){

                    //取得
                    $p->sendMessage("読込中です...");
                    $target = $dats[$name][$status[$name]["show"]];
                    $this->getThread($target);
                    
                    //send
                    $p->sendMessage("前へ : 木ピッケル\n次へ : 石ピッケル\nスレッド選択へ : 金ピッケル");
                    $p->sendMessage($this->getResponceString($target));//msg

                    //変更
                    $status[$name]["status"] = "thread";
                    $status[$name]["dat"] = $target;
                    $status[$name]["res"] = 0;
                }

                break;
            case ItemIds::GOLDEN_PICKAXE:
                //スレッドを抜ける

                if(isset($threads[$name]) && $status[$name]["status"] == "thread"){
                    $status[$name]["status"]="list";
                    //send
                    $p->sendMessage("前へ : 木ピッケル\n次へ : 石ピッケル\n選択 : 鉄ピッケル");
                    $p->sendMessage("§3thread §7>>> §f".$threads[$name][$status[$name]["show"]]);
                }else{
                    $p->sendMessage("§eまずダイヤピッケルでスレッド一覧を更新してください");
                }

                break;
            case ItemIds::DIAMOND_PICKAXE:
                //更新
                
                if($status[$name]["status"]=="none" || $status[$name]["status"]=="list"){
                    $status[$name]["status"] = "list";
                    $status[$name]["show"] = 0;
    
                    //更新
                    $p->sendMessage("スレッドを更新しています...");
                    $this->getThreads($name);
    
                    //send
                    $p->sendMessage("前へ : 木ピッケル\n次へ : 石ピッケル\n選択 : 鉄ピッケル");
                    $p->sendMessage("§3thread §7>>> §f".$threads[$name][0]);
                }else{
                    $p->sendMessage("更新中です...");
                    $this->getThread($status[$name]["dat"]);
                    $p->sendMessage("更新しました");
                }
                break;
        }
    }

    public function getThreads($name){

        //取得
        $context = stream_context_create(array(
            'http' => array('ignore_errors' => true)
        ));
        $html = file_get_contents("http://swallow.5ch.net/livejupiter/subback.html", false, $context);
        
        $html = mb_convert_encoding($html,"utf-8","Shift_JIS");//文字化け回避

        //抜き出し
        preg_match_all("{<a href=\"([0-9]{10}).*?>[0-9]{0,3}:(.*?)</a>}", $html , $match);
        
        //代入
        global $dats,$threads;

        $dats[$name] = $match[1];
        $threads[$name] = $match[2];
    }

    public function getThread($dat){

        //取得
        $context = stream_context_create(array(
            'http' => array('ignore_errors' => true)
        ));
        $html = file_get_contents("https://swallow.5ch.net/test/read.cgi/livejupiter/".$dat."/", false, $context);
        
        $html = mb_convert_encoding($html,"utf-8","Shift_JIS");//文字化け回避
        
        $pattern = '{<div class="post" id="([0-9]{0,3})" .*? data-userid="(ID:.*?)" .*?><div class="meta"><span class="number">.*?</span><span class="name"><b>(.*?)</b></span><span class="date">(.*?)</span><span class="uid">.*?</span></div><div class="message"><span class="escaped">(.*?)</span></div></div>}';

        //抜き出し
        preg_match_all($pattern , $html , $match);

        global $data;
        //number,id,name,date,message
        $data[$dat] = [$match[1],$match[2],$match[3],$match[4],$match[5]];
    
        //idにsageつける輩とmessageにurl貼る輩対処
    }

    public function getResponceString($target,$num=0)
    {   
        global $data;

        //url(あとエスケープ直すべきかも)
        $msg = str_replace("<br>","\n",$data[$target][4][$num]);
        $msg = preg_replace("{<.*?>(.*?)<.*?>}", "$1", $msg);

        //sage消す
        $id = preg_replace("{<.*?>(.*?)<.*?>}", "$1", $data[$target][1][$num]);
        $name = preg_replace("{<.*?>(.*?)<.*?>}", "$1", $data[$target][2][$num]);


        $str =  $data[$target][0][$num]." : ".//num
                $id." : §2".//id
                $name." §f: ".//name
                $data[$target][3][$num]."\n".//date
                $msg;//msg
                return $str;
    }
}
