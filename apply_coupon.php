<?php
require_once "connectdb.php";
header('Content-Type: application/json; charset=utf-8');

$code = trim($_POST["coupon_code"] ?? '');
$total = (float)($_POST["total"] ?? 0);

$response = ["success"=>false,"message"=>"","discount"=>0];

if($code==""){
  $response["message"]="❌ กรุณาใส่รหัสคูปอง";
  echo json_encode($response); exit;
}

$sql="SELECT * FROM coupons WHERE code=? AND status='active' 
      AND CURDATE() BETWEEN start_date AND end_date 
      AND used_count < usage_limit";
$stmt=$conn->prepare($sql);
$stmt->bind_param("s",$code);
$stmt->execute();
$coupon=$stmt->get_result()->fetch_assoc();

if(!$coupon){
  $response["message"]="❌ คูปองไม่ถูกต้องหรือหมดอายุ";
}else{
  if($total < $coupon["min_spend"]){
    $response["message"]="ยอดขั้นต่ำคือ ".number_format($coupon["min_spend"],2)." บาท";
  }else{
    if($coupon["discount_type"]=="percent"){
      $discount = $total * ($coupon["discount_value"]/100);
      if($coupon["max_discount"] && $discount>$coupon["max_discount"])
        $discount=$coupon["max_discount"];
    }else{
      $discount=$coupon["discount_value"];
    }
    $response=["success"=>true,"message"=>"✅ ใช้คูปองสำเร็จ ลด ".number_format($discount,2)." บาท","discount"=>$discount];
  }
}
echo json_encode($response);
