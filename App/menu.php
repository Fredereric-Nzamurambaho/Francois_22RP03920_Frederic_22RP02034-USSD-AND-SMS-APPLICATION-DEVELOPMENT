<?php
require_once 'sms.php';
require_once 'db.php';
require_once 'util.php';

class Menu {
    protected $text;
    protected $sessionId;
    protected $phoneNumber;
    protected $conn;
    function __construct($text, $sessionId, $phoneNumber, $conn) {
        $this->text = $text;
        $this->sessionId = $sessionId;
        $this->phoneNumber = $phoneNumber;
        $this->conn = $conn;
    }

    public function mainMenuUnregistered() {
        echo "CON Welcome to XYZ MOMO\n1. Register User\n2. Register Agent";
    }

    public function menuRegister($textArray) {
        $level = count($textArray);
    
        if ($level == 1) {
            echo "CON Enter your full User name";
        } elseif ($level == 2) {
            echo "CON Enter your PIN";
        } elseif ($level == 3) {
            echo "CON Re-enter your PIN";
        } elseif ($level == 4) {
            $name = trim($textArray[1]);
            $pin = trim($textArray[2]);
            $confirmPin = trim($textArray[3]);
     
            // Check if PINs match
            if ($pin !== $confirmPin) {
                echo "END PINs do not match. Please try again.";
                return;
            }
    
            // Check if phone is already registered
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE phone_number = ?");
            $stmt->execute([$this->phoneNumber]);
            if ($stmt->rowCount() > 0) {
                echo "END This phone number is already registered.";
                return;
            }
    
            // Hash PIN and insert into database
            
            $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("INSERT INTO users (phone_number, full_name, pin, balance) VALUES (?, ?, ?, 500)");
            if ($stmt->execute([$this->phoneNumber, $name, $hashedPin])) {
          
            echo "END Dear $name, you have successfully registered.Initial balance Account is:".Util::$user_balance."Rwf";
            } else {
                echo "END Registration failed. Please try again.";
            }
        } else {
            echo "END Invalid input. Please try again.";
        }
    }

    
    public function RegisterAgent($textArray){
    $level = count($textArray);

    if ($level == 1) {
        echo "CON Enter Your Full Agent Name:"; // Use "CON" to prompt next input
        return;
    } elseif ($level == 2) {
        echo "CON Enter Agent Code:";
        return;
    } elseif ($level == 3) {
        $name = trim($textArray[1]);
        $Code = trim($textArray[2]);

        // Check if phone is already registered
        $stmt = $this->conn->prepare("SELECT * FROM agents WHERE phone_number = ?");
        $stmt->execute([$this->phoneNumber]);

        if ($stmt->rowCount() > 0) {
            echo "END This phone number is already registered.";
            return;
        }

        // Register new agent
        $stmt = $this->conn->prepare("INSERT INTO agents (phone_number, full_name, Agent_Code, balance) VALUES (?, ?, ?, 500)");
        if ($stmt->execute([$this->phoneNumber, $name, $Code])) {
            echo "END Dear $name, you have successfully registered. Your Code is: $Code. Thank you!";
        } else {
            echo "END Registration failed. Please try again.";
        }
    }
   }

    public function mainMenuRegistered() {
        echo "CON Welcome back to XYZ MOMO\n1. Send Money\n2. Withdraw Money\n3. Check Balance";
    }

    public function menuSendMoney($textArray) {
        $level = count($textArray);

        if ($level == 1) {
            echo "CON Enter recipient phone number";
        } elseif ($level == 2) {
            echo "CON Enter amount";
        } elseif ($level == 3) {
            echo "CON Enter agent code";
        } elseif ($level == 4) {
            echo "CON Enter PIN";
        } elseif ($level == 5) {
            list(, $recipient, $amount, $agentCode, $pin) = $textArray;

            // Verify agent code
            $stmt = $this->conn->prepare("SELECT * FROM agents WHERE Agent_Code = ?");
            $stmt->execute([$agentCode]);
            $agent = $stmt->fetch();

            if (!$agent) {
                echo "END Invalid agent code.";
                return;
            }

            $stmt = $this->conn->prepare("SELECT * FROM users WHERE phone_number = ?");
            $stmt->execute([$this->phoneNumber]);
            $sender = $stmt->fetch();

            if (!$sender || !password_verify($pin, $sender['pin'])) {
                echo "END Incorrect PIN.";
                return;
            }

            $stmt = $this->conn->prepare("SELECT * FROM users WHERE phone_number = ?");
            $stmt->execute([$recipient]);
            $receiver = $stmt->fetch();

            if (!$receiver) {
                echo "END Recipient does not exist.";
                return;
            }

            if ($sender['balance'] < $amount) {
                echo "END Insufficient balance.";
                return;
            }

            $response = "CON Do you want to send Amount of $amount RWF to $recipient?\n";
            $response .= "1. Confirm\n";
            $response .= "2. Cancel\n";
            $response .= "98. Back\n";
            $response .= "99. Main menu\n";
            echo $response;
        } elseif ($level == 6 && $textArray[5] == "1") {
            list(, $recipient, $amount, $agentCode, $pin) = $textArray;

            $this->conn->beginTransaction();

            $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE phone_number = ?")
                ->execute([$amount, $this->phoneNumber]);

            $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE phone_number = ?")
                ->execute([$amount, $recipient]);

            $stmt = $this->conn->prepare("SELECT phone_number FROM agents WHERE Agent_Code = ?");
            $stmt->execute([$agentCode]);
            $agent = $stmt->fetch();

            $this->conn->prepare("INSERT INTO transactions (sender_phone, recipient_phone, amount, transaction_type, agent_phone) VALUES (?, ?, ?, 'SEND', ?)")
                ->execute([$this->phoneNumber, $recipient, $amount, $agent['phone_number']]);

            $this->conn->commit();

            $stmt = $this->conn->prepare("SELECT full_name FROM users WHERE phone_number = ?");
            $stmt->execute([$recipient]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $recipientName = $row['full_name'];

            echo "END You have sent $amount Rwf to $recipientName ($recipient) successfully.";
        } elseif ($level == 6 && $textArray[5] == "2") {
            echo "END Transaction cancelled.";
        } elseif ($level == 6 && $textArray[5] == "98") {
            echo "END Going back one step";
        } elseif ($level == 6 && $textArray[5] == "99") {
            echo "END Returning to main menu";
        } else {
            echo "END Invalid option.";
        }
    }



    public function menuCheckBalance($textArray) {
        $level = count($textArray);

        if ($level == 1) {
            echo "CON Enter your PIN";
        } 
        elseif ($level == 2) {
            $pin = $textArray[1];

            $stmt = $this->conn->prepare("SELECT * FROM users WHERE phone_number = ?");
            $stmt->execute([$this->phoneNumber]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($pin, $user['pin'])) {
                echo "END Incorrect PIN.";
                return;
            }

            $balance = $user['balance'];
            $message = "Hello, your current balance is: $balance Rwf.";

            $sms = new Sms();
            $sms->sendSMS($message, $this->phoneNumber);

            //echo "END Your balance is: $balance Rwf (also sent via SMS)";
            echo "END Your balance also sent via SMS";
        }
    }



    public function menuWithdrawMoney($textArray) {
        $level = count($textArray);

        if ($level == 1) {
            echo "CON Enter amount";
        } elseif ($level == 2) {
            echo "CON Enter agent phone number";
        } elseif ($level == 3) {
            echo "CON Enter your PIN";
        } elseif ($level == 4) {
            list(, $amount, $agentPhone, $pin) = $textArray;

            echo "CON You are about to withdraw $amount Rwf via agent $agentPhone.\n";
            echo "1. Confirm\n2. Cancel\n3. Back\n4. Back to Main Menu";
        } 
        
        elseif ($level == 5) {
            list(, $amount, $agentPhone, $pin, $option) = $textArray;
            $option = intval($option);

            if ($option === 1) {
                $stmt = $this->conn->prepare("SELECT * FROM users WHERE phone_number = ?");
                $stmt->execute([$this->phoneNumber]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($pin, $user['pin'])) {
                    echo "END Incorrect PIN.";
                    return;
                }

                if ($user['balance'] < $amount) {
                    echo "END Insufficient balance.";
                    return;
                }

                $stmt = $this->conn->prepare("SELECT * FROM agents WHERE phone_number = ?");
                $stmt->execute([$agentPhone]);
                $agent = $stmt->fetch();

                if (!$agent) {
                    echo "END Agent not found.";
                    return;
                }

                $this->conn->beginTransaction();

                $this->conn->prepare("UPDATE users SET balance = balance - ? WHERE phone_number = ?")
                    ->execute([$amount, $this->phoneNumber]);

                $this->conn->prepare("INSERT INTO transactions (sender_phone, amount, transaction_type, agent_phone) VALUES (?, ?, 'WITHDRAW', ?)")
                    ->execute([$this->phoneNumber, $amount, $agentPhone]);

                $this->conn->commit();

                echo "END You have successfully withdrawn $amount Rwf via agent $agentPhone.";
            } elseif ($option === 2) {
                echo "END Transaction cancelled.";
            } else {
                echo "END Invalid option.";
            }
        }
    }

    public function middleware($text){
        return $this->goBack($this->goBackMenu($text));
    }
    
    public function goBack($text){
        $explodedText=explode("*",$text);
        while(array_search('98',$explodedText)!=false){
            $firstIndex=array_search('98',$explodedText);
            array_splice($explodedText,$firstIndex-1,2);
        }
      return join("*",$explodedText);
    }


    public function goBackMenu($text){
        $explodedText=explode("*",$text);
        while(array_search('99',$explodedText)!=false){
            $firstIndex=array_search('99',$explodedText);
            $explodedText = array_slice($explodedText,$firstIndex+1);
            
        }
      return join("*",$explodedText);
    }
}
?>
