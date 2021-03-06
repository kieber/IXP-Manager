<?php

namespace Entities;

/*
 * Copyright (C) 2009 - 2019 Internet Neutral Exchange Association Company Limited By Guarantee.
 * All Rights Reserved.
 *
 * This file is part of IXP Manager.
 *
 * IXP Manager is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, version v2.0 of the License.
 *
 * IXP Manager is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GpNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

use Datetime;

use Entities\{
    User as UserEntity,
    UserRememberTokens as UserRememberTokensEntity,
};

/**
 * UserRememberTokens
 */
class UserRememberTokens
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $device;

    /**
     * @var string
     */
    private $ip;

    /**
     * @var \DateTime
     */
    private $created;

    /**
     * @var \DateTime
     */
    private $expires;

    /**
     * @var int
     */
    private $id;

    /**
     * @var UserEntity
     */
    private $User;

    /**
     * @var string
     */
    private $session_id;

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken( string $token ): void
    {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getDevice(): string
    {
        return $this->device;
    }

    /**
     * @param string $device
     */
    public function setDevice( string $device ): void
    {
        $this->device = $device;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     */
    public function setId( string $ip ): void
    {
        $this->ip = $ip;
    }

    /**
     * @return DateTime
     */
    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * @param DateTime $created
     */
    public function setCreated( DateTime $created ): void
    {
        $this->created = $created;
    }

    /**
     * @return DateTime
     */
    public function getExpires(): DateTime
    {
        return $this->expires;
    }

    /**
     * @param DateTime $expires
     */
    public function setExpires( DateTime $expires ): void
    {
        $this->expires = $expires;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return UserEntity
     */
    public function getUser(): UserEntity
    {
        return $this->User;
    }

    /**
     * @param UserEntity $User
     */
    public function setUser( UserEntity $User ): void
    {
        $this->User = $User;
    }

    /**
     * Set Password Security
     *
     * @param string|null $session_id
     * @return UserRememberTokensEntity
     */
    public function setSessionId( $session_id )
    {
        $this->session_id = $session_id;

        return $this;
    }

    /**
     * Get Password Security
     *
     * @return string
     */
    public function getSessionId()
    {
        return $this->session_id;
    }
}
