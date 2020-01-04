<?php
namespace daemons;

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;

/**
 * Phone daemon class
 */
class Phone extends AbstractDaemon
{
    private $msg_queue;
    private $queue_id;
    private $last_message_at;

    public function __construct($phone_number)
    {
        $this->queue_id = (int) mb_substr($phone_number, 1);
        
        $name = 'RaspiSMS Phone ' . $phone_number;

        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler(PWD_LOGS . '/raspisms.log', Logger::DEBUG));
        
        $pid_dir = PWD_PID;
        $additional_signals = [];
        $uniq = true; //Main server should be uniq

        //Construct the server and add SIGUSR1 and SIGUSR2
        parent::__construct($name, $logger, $pid_dir, $additional_signals, $uniq);
        
        parent::start();
    }


    public function run()
    {
        if ( (microtime(true) - $this->last_message_at) > 5 * 60 )
        {
            $this->is_running = false;
            $this->logger->info("End running");
            return true;
        }

        $msgtype = null;
        $maxsize = 409600;
        $message = null;

        msg_receive($this->msg_queue, SEND_MSG, $msgtype, $maxsize, $message);

        if (!$message)
        {
            return true;
        }
        
        $bdd = \descartes\Model::_connect(DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD, 'UTF8');
        $internal_sended = new \controllers\internals\Sended($bdd);

        //If message received, update last message time
        $this->last_message_at = microtime(true);

        //Register message as sended
        $now = new \DateTime();
        $now = $now->format('Y-m-d H:i:s');
        $internal_sended->create($now, $message['text'], $message['origin'], $message['destination'], $message['flash']);

        //Close bdd
        $bdd = null;
        $internal_sended = null;

        $this->logger->info('Send message : ' . json_encode($message));
    }


    public function on_start()
    {
        //Set last message at to construct time
        $this->last_message_at = microtime(true);

        $this->msg_queue = msg_get_queue($this->queue_id);
        
        $this->logger->info("Starting Phone with pid " . getmypid());
    }


    public function on_stop() 
    {
        $this->logger->info("Closing queue : " . $this->queue_id);
        msg_remove_queue($this->msg_queue); //Delete queue on daemon close

        $this->logger->info("Stopping Phone with pid " . getmypid ());
    }


    public function handle_other_signals($signal)
    {
        $this->logger->info("Signal not handled by " . $this->name . " Daemon : " . $signal);
    }
}