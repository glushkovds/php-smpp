<?php

namespace PhpSmpp\Pdu;

/**
 * Primitive class for encapsulating PDUs
 * @author hd@onlinecity.dk
 */
class Pdu
{
	public $id;
	public $status;
	public $sequence;
	public $body;
	
	/**
	 * Create new generic PDU object
	 * 
	 * @param integer $id
	 * @param integer $status
	 * @param integer $sequence
	 * @param string $body
	 */
	public function __construct($id, $status, $sequence, $body)
	{
		$this->id = $id;
		$this->status = $status;
		$this->sequence = $sequence;
		$this->body = $body;
	}

    public function getBinary()
    {
        $length = strlen($this->body) + 16;
        $header = pack("NNNN", $length, $this->id, $this->status, $this->sequence);
        return $header . $this->body;
    }

}
