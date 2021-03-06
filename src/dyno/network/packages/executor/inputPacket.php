<?php
/**
 * ________
 * ___  __ \____  ______________
 * __  / / /_  / / /_  __ \  __ \
 * _  /_/ /_  /_/ /_  / / / /_/ /
 * /_____/ _\__, / /_/ /_/\____/
 *         /____/
 *
 * This program is free: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is based on PocketMine Software and Synapse.
 *
 * @copyright (c) 2020
 * @author Y&SS-YassLV
 */

namespace dyno\network\packages\executor;

use dyno\network\packages\{
    DataPacket, Info
};

class inputPacket extends DataPacket
{
    public const NETWORK_ID = Info::INPUT_PACKET;

    public const GETTER_TYPE_ARRAY = 0;
    public const GETTER_TYPE_OBJECT = 1; //stdClass

    /** @var string|null */
    public $tunnelKey = null; //Unique key given if null
    /** @var string */
    public $input;
    /** @var string */
    public $pluginClass = "";
    /** @var array */
    public $want = [];
    /** @var int */
    public $getterType = self::GETTER_TYPE_ARRAY;
    /**
     * @var bool
     * If you make several requests, this is not really advisable
     * it can reverse the results if one async task is faster than another!
     */
    public $internalDynoWriteAsyncFile = false;

    protected function encodePayload()
    {
        $this->putString($this->tunnelKey ?? self::uniqueTunnelKey());
        $this->putString($this->input);
        $this->putString($this->pluginClass);
        $this->putString(json_encode($this->want));
        $this->putInt($this->getterType);
        $this->putBool($this->internalDynoWriteAsyncFile);
    }

    /**
     * @return string
     */
    private static function uniqueTunnelKey(): string
    {
        return uniqid();
    }

    protected function decodePayload()
    {
        $this->tunnelKey = $this->getString();
        $this->input = $this->getString();
        $this->pluginClass = $this->getString();
        $this->want = json_decode($this->getString(), true);
        $this->getterType = $this->getInt();
        $this->internalDynoWriteAsyncFile = $this->getBool();
    }
}