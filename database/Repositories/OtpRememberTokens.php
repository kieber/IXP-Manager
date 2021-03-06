<?php

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

namespace Repositories;

use D2EM, Datetime;

use Doctrine\ORM\EntityRepository;

use Entities\{
    OtpRememberTokens as OtpRememberTokensEntity,
    User as UserEntity
};

use IXP\Utils\IpAddress;
use Wolfcast\BrowserDetection;


/**
 * Otp Remember Tokens
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class OtpRememberTokens extends EntityRepository
{

    /**
     * Get all OTP remember token
     *
     * @param \stdClass $feParams
     * @param int|null $userid
     * @param int|null $id
     *
     * @return array Array of infrastructures (as associated arrays) (or single element if `$id` passed)
     *
     * @see \IXP\Http\Controllers\Doctrine2Frontend
     */
    public function getAllForFeList( \stdClass $feParams, int $userid, int $id = null )
    {
        $dql = "SELECT  ort.id         AS id, 
                        ort.device     AS device, 
                        ort.ip         AS ip, 
                        ort.created    AS created, 
                        ort.expires    AS expires 
                FROM Entities\\OtpRememberTokens ort
                WHERE ort.User = " . (int)$userid;

        if( $id ) {
            $dql .= " ort ort.id = " . (int)$id;
        }

        if( isset( $feParams->listOrderBy ) ) {
            $dql .= " ORDER BY " . $feParams->listOrderBy . ' ';
            $dql .= isset( $feParams->listOrderByDir ) ? $feParams->listOrderByDir : 'ASC';
        }

        return $this->getEntityManager()->createQuery( $dql )->getArrayResult();
    }

    /**
     * Replace "remember me" token with new token.
     *
     * @param $identifier
     * @param string $token
     * @param string $newToken
     * @param int $expire
     *
     * @return void
     *
     * @throws
     */
    public function replaceRememberToken($identifier, $token, $newToken, $expire)
    {
        if (! $ort = D2EM::getRepository( OtpRememberTokensEntity::class )->findOneBy( [ "User" => $identifier, "token" => $token ]  ) ) {
            return null;
        }
        $ort->setToken( $newToken );
        $ort->setExpires( new DateTime( "+$expire minutes" ) );

        D2EM::flush();
    }

    /**
     * Create the Otc Remember Token
     *
     * @param UserEntity $user
     * @param string $token
     *
     * @throws
     */
    public function createRememberToken( UserEntity $user, string $token )
    {
        $expire = config( "google2fa.remember_me_expire" );

        $ort = new OtpRememberTokensEntity;
        D2EM::persist( $ort );

        $browser = new BrowserDetection();

        $ort->setUser( $user );
        $ort->setToken( $token );
        $ort->setExpires( new DateTime( "+$expire minutes" ) );
        $ort->setCreated( new DateTime() );
        $ort->setDevice( $browser->getPlatform() . " " . $browser->getPlatformVersion(true) . " / " . $browser->getName() . " " . $browser->getVersion() );
        $ort->setId( IpAddress::getIp() );

        $user->addOtcRememberTokens( $ort );

        D2EM::flush();
    }

    /**
     * Delete Remember token
     *
     * @param $identifier
     * @param $token
     *
     * @return void
     *
     * @throws
     */
    public function deleteRememberToken( $identifier, $token )
    {
        return $this->getEntityManager()->createQuery( "DELETE FROM Entities\\OtpRememberTokens ort WHERE ort.User = ?1 AND ort.token = ?2" )
                ->setParameter(1, $identifier )
                ->setParameter(2, $token )
                ->execute();
    }

    /**
     * Purge old or expired "remember me" tokens.
     *
     * @param  mixed $identifier
     * @param  bool $expired
     * @return null
     *
     * @throws
     */
    public function purgeRememberTokens( $identifier, $expired = false )
    {
        $sql = "DELETE FROM Entities\\OtpRememberTokens ort WHERE ort.User = " . $identifier;

        if ( $expired ) {
            $now = new DateTime();
            $sql .= " AND ort.expires < '" . $now->format( 'Y-m-d H:i:s' ) . "'";
        }

        $this->getEntityManager()->createQuery( $sql )->execute();
    }
}
