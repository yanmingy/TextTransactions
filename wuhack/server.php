<?php

require "/home/ymy/public_html/twilio-php-master/twilio-php-master/Services/Twilio.php";

// set your AccountSid and AuthToken from www.twilio.com/user/account
$AccountSid = "ACc94122bb43825bd4a20086cb3b3f9127";
$AuthToken = "518a6f446259a741238743d7e78d0683";

$client = new Services_Twilio($AccountSid, $AuthToken);


try {

    file_put_contents('php://stderr', print_r("THERE IS A NEW TEST" . rand(1, 100) . " \n", TRUE));
    $sentfromphone = $_REQUEST['Body'];
    $fromNum = $_REQUEST['From'];
    $arr = explode(" ", $sentfromphone);

    if (count($arr) > 0) {
        $response = handleResponse($arr, $fromNum, $client);
    }

    /*
      $message = $client->account->messages->create(array(
      "From" => "+18322624459",
      "To" => $fromNum,
      "Body" => $response . " \nThank you",
      ));
     * 
     */
} catch (Services_Twilio_RestException $e) {
    echo $e->getMessage();
}

function sendMessage($content, $client, $toNumber) {
    $message = $client->account->messages->create(array(
        "From" => "+18322624459",
        "To" => $toNumber,
        "Body" => $content . " \nThank you",
    ));
}

function handleResponse($arr, $fromAcct, $client) {
    if ($arr[0] == "Xfer") { //expects XFER <AMT> <ACCT>
        sendMessage(transfer($arr, $fromAcct), $client, $fromAcct);
    } else if ($arr[0] == "LoanApp") { //expects LoanApp <AMT> <Backer>
        $response = loanApply($arr, $fromAcct);
        $message = $client->account->messages->create(array(
            "From" => "+18322624459",
            "To" => $arr[2],
            "Body" => $response . " \n Thank you",
        ));

        sendMessage("A message has been sent to your backer.", $client, $fromAcct);
    } else if ($arr[0] == "LoanFul") {//expects LoanFul <HASH>
        return loanFulfill($arr, $fromAcct, $client);
    } else if ($arr[0] == "PayLoan") {//expects payLoan <HASH>
        $response = payLoan($arr, $fromAcct, $client);
        $responseArr = explode(":", $response); //need to reword transfer, which gets ultimately returned in payLoan
        if (count($responseArr) > 1) {
            $response = "Please confirm repayment of loan " . $arr[1] . " by replying with: " . $responseArr[1];
        }
        $message = $client->account->messages->create(array(
            "From" => "+18322624459",
            "To" => $fromAcct,
            "Body" => $response . " \n Thank you",
        ));
    } else if ($arr[0] == "MyLoan") {//no params, just sends a listing of all loans to an acct
        return myLoan($arr, $fromAcct, $client);
    } else if ($arr[0] == "Verify") {//expects verify <HASH>
        verify($arr, $fromAcct, $client);
    } else if ($arr[0] == "Search") {//expects search [amt]
        return searchLoans($arr, $fromAcct,$client);
    } else if ($arr[0] == "Balance") {
        myBalance($arr, $fromAcct, $client);
    } else {
        sendMessage("Malformed message, try again", $client, $fromAcct);
    }
}

function transfer($arr, $fromAcct) {
    require 'database.php';
    if (count($arr) == 3) {
        $transferAmt = $arr[1];
        $toAcct = $arr[2]; //Destination account
        if (is_numeric($transferAmt) && is_numeric($toAcct)) {
            $stmt = ($mysqli->prepare("SELECT balance from acct where acct=?"));
            if (!$stmt) {
                file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
                return "failure";
                exit;
            }
            $stmt->bind_param('s', $fromAcct);
            $stmt->execute();

            $stmt->bind_result($amt);
            $stmt->fetch();
            $stmt->close();
            if ($amt < $transferAmt) {
                return "Insufficient funds";
            } else {
                $transType = "MM";
                $hashVal = $transType . substr(hash("sha256", rand(1, 99) . $toAcct . $fromAcct), rand(0, 57), 6);

                $stmt = ($mysqli->prepare("insert into code (type,hash,acctTo,acctFrom,amnt) values (?, ?, ?, ?, ?)"));
                if (!$stmt) {
                    file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
                    return "failure";
                    exit;
                }
                $stmt->bind_param('ssiii', $transType, $hashVal, $toAcct, $fromAcct, $transferAmt);
                $stmt->execute();
                $stmt->close();




                return "Please confirm your loan by replying with: Verify " . $hashVal;
            }
        } else {
            return "Malformed message, try again";
        }
        return "Malformed message, try again";
    }
}

function loanApply($arr, $fromAcct) {
    require 'database.php';

    if (count($arr) == 3) {
        $loanamt = $arr[1];
        $backerAcct = $arr[2]; //Destination account
        if (is_numeric($loanamt) && is_numeric($backerAcct)) {
            $stmt = ($mysqli->prepare("SELECT balance from acct where acct=?"));
            if (!$stmt) {
                file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
                return "failure";
                exit;
            }
            $stmt->bind_param('s', $fromAcct);
            $stmt->execute();

            $stmt->bind_result($amt);
            $stmt->fetch();
            $stmt->close();
            if ($amt < ($loanamt * 0.15)) {//15% of loan as colllateral
                return "Insufficient funds" . $amt;
            } else {
                $transType = "LL";
                $hashVal = $transType . substr(hash("sha256", rand(1, 99) . $backerAcct . $fromAcct), rand(0, 57), 6);

                $stmt = ($mysqli->prepare("insert into code (type,hash,acctTo,Backer1,amnt) values (?, ?, ?, ?, ?)"));
                if (!$stmt) {
                    file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
                    return "failure";
                    exit;
                }
                $stmt->bind_param('ssiii', $transType, $hashVal, $fromAcct, $backerAcct, $loanamt);
                $stmt->execute();
                $stmt->close();




                return "Please confirm your backing of a $" . $loanamt . " loan to " . $fromAcct . " by replying with: Verify " . $hashVal;
            }
        } else {
            return "Malformed message, try again";
        }
        return "Malformed message, try again";
    }
}

function loanFulfill($arr, $fromAcct, $client) {

    require 'database.php';
    $hash = $arr[1];
    $stmt = ($mysqli->prepare("select prin,acct from loan where hash = ?"));
    if (!$stmt) {
        file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
        return "failure";
        exit;
    }
    $stmt->bind_param('s', $hash);
    $stmt->execute();

    $stmt->bind_result($prin, $loanReceiver);
    $stmt->fetch();
    $stmt->close();
    if (!$prin) {
        $stmt = ($mysqli->prepare(" update loan set prin=? where hash=?"));
        if (!$stmt) {
            file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
            return "failure";
            exit;
        }
        $stmt->bind_param('is', $fromAcct, $hash);
        $stmt->execute();
        $stmt->close();
        sendMessage("You are now lending to " . $loanReceiver . " for loan " . $hash, $client, $fromAcct);
    } else {
        sendMessage("This loan is not available", $client, $fromAcct);
    }
}

function payLoan($arr, $fromAcct, $client) {
    require 'database.php';
    $hash = $arr[1];
    $stmt = ($mysqli->prepare("select acct,backer1,prin,amnt,paid from loan where hash = ?"));
    if (!$stmt) {
        file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
        return "failure";
        exit;
    }
    $stmt->bind_param('s', $hash);
    $stmt->execute();

    $stmt->bind_result($acct, $backer, $prin, $loan, $paid);
    $stmt->fetch();
    $stmt->close();

    if (!$paid || !$prin) {


        $stmt = ($mysqli->prepare("update loan set paid = ? where hash = ?"));
        if (!$stmt) {
            file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
            return "failure";
            exit;
        }
        $val = 1;
        $stmt->bind_param('is', $val, $hash);
        $stmt->execute();

        $stmt->bind_result($acct, $backer, $prin, $loan, $paid);
        $stmt->fetch();
        $stmt->close();
        sendMessage("Loan has been repaid", $client, $prin);
        $tempArr = array("XFER", $loan, $prin);
        return transfer($tempArr, $fromAcct);
    } else {
        sendMessage("Loan does not have lender or has already been repaid", $client, $fromAcct);
    }
}

function myLoan($arr, $fromAcct, $client) {
    require 'database.php';
    $stmt = ($mysqli->prepare("select hash,prin,amnt from loan where acct=?"));
    if (!$stmt) {
        file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
        return "failure";
        exit;
    }
    $stmt->bind_param('s', $fromAcct);
    $stmt->execute();

    $stmt->bind_result($loanHash, $prin, $loanAmt);
    while ($stmt->fetch()) {
        $response = "";
        if ($prin) {
            $response = $loanHash . ": $" . $loanAmt . " owed to " . $prin;
        } else {
            $response = "No lender yet for " . $loanHash . ": $" . $loanAmt;
        }
        sendMessage($response, $client, $fromAcct);
    }
    $stmt->close();
    $tempArr = array("XFER", $loanAmt, $prin);
    sendMessage("All Loans", $client, $fromAcct);
}

function verify($arr, $fromAcct, $client) {
    require 'database.php';
    $hash = $arr[1];


    $stmt = ($mysqli->prepare("select acctTo,acctFrom,amnt,type,backer1 from code where hash = ?"));
    if (!$stmt) {
        file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
        return "failure";
        exit;
    }
    $stmt->bind_param('s', $hash);
    $stmt->execute();

    $stmt->bind_result($acctTo, $acctFrom, $amnt, $type, $backer);
    $stmt->fetch();
    $stmt->close();

    file_put_contents('php://stderr', print_r("My hash is " . $hash . " and my type is " . $type . "and my acctTo is " . $acctTo . "\n", TRUE));

    if ($type == "MM") {
        $mysqli->autocommit(FALSE);
        $stmt = ($mysqli->prepare("update  acct set balance=balance-? where acct = ?"));
        if (!$stmt) {
            file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
            return "failure";
            exit;
        }
        $stmt->bind_param('ii', $amnt, $acctFrom);
        $stmt->execute();

        $stmt = ($mysqli->prepare(" update acct set balance=balance+? where acct = ?"));
        if (!$stmt) {
            file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
            return "failure";
            exit;
        }
        $stmt->bind_param('ii', $amnt, $acctTo);
        $stmt->execute();
        $mysqli->commit();
        $stmt->close();

        $mysqli->autocommit(TRUE);



        $stmt = ($mysqli->prepare("delete from code where hash = ?"));
        if (!$stmt) {
            file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
            return "failure";
            exit;
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stmt->close();

        //delete from code where hash = ?

        sendMessage("Transaction " . $hash . " released.", $client, $fromAcct);
    } else if ($type == "LL") {
        file_put_contents('php://stderr', print_r("I entered LL with hash" . $hash . " \n", TRUE));
        $stmt = ($mysqli->prepare("insert into loan (acct,backer1,amnt,hash) values (?, ?, ?,?)"));
        if (!$stmt) {
            file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
            return "failure";
            exit;
        }
        $stmt->bind_param('iiis', $acctTo, $backer, $amnt, $hash);
        $stmt->execute();
        $stmt->close();

        $stmt = ($mysqli->prepare("delete from code where hash = ?"));
        if (!$stmt) {
            file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
            return "failure";
            exit;
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stmt->close();

        sendMessage("Loan Released: " . $hash, $client, $acctTo);
    } else {
        return "invalid loan";
    }
    //
}

function searchLoans($arr, $fromAcct,$client) {
     require 'database.php';

    $stmt = ($mysqli->prepare("select hash,amnt from loan where amnt <= ? && paid!=1"));
    if (!$stmt) {
        file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
        return "failure";
        exit;
    }
    $stmt->bind_param('i', $arr[1]);
    $stmt->execute();

    $stmt->bind_result($hash,$amnt);
    while($stmt->fetch()){
        sendMessage($hash.": $".$amnt,$client,$fromAcct);
    }
    $stmt->close();

    sendMessage("Available Loans" . $balance, $client, $fromAcct);
}

function myBalance($arr, $fromAcct, $client) {
    require 'database.php';


    $stmt = ($mysqli->prepare("select balance from acct where acct =  ?"));
    if (!$stmt) {
        file_put_contents('php://stderr', print_r("MySQl statement prep failed \n", TRUE));
        return "failure";
        exit;
    }
    $stmt->bind_param('s', $fromAcct);
    $stmt->execute();

    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();

    sendMessage("Your balance is $" . $balance, $client, $fromAcct);
}

//select acctTo,acctFrom,amnt,type from code where hash = "MM9a347b";
?>