<?php

namespace Repositories;

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
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

use D2EM, DateTime, Hash, Log;

use Illuminate\Support\Str;

use Entities\{
    CustomerToUser      as CustomerToUserEntity,
    Session             as SessionEntity,
    User                as UserEntity,
    UserLoginHistory    as UserLoginHistoryEntity,
    UserRememberTokens  as UserRememberTokensEntity
};

use Doctrine\ORM\EntityRepository;

use IXP\Events\User\{
    UserAddedToCustomer as UserAddedToCustomerEvent,
    UserCreated as UserCreatedEvent
};

/**
 * User
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class User extends EntityRepository
{

    /**
     * Get all users as an array. Optionally limited to a given privilege.

     * @param int $priv If not null, limit to given privilege level
     * @return array
     */
    public function asArray( int $priv = null ) {

        return $this->getEntityManager()->createQuery(
                "SELECT u FROM Entities\User u" . ( is_int($priv) ? ' WHERE u.privs = ' . $priv : '' ) )
            ->getArrayResult();
    }

    /**
     * Return an array of users with their last login time ordered from most recent to oldest.
     *
     * As an example, an element of the returned array contains:
     *
     *     [0] => array(6) {
     *         ["attribute"] => string(18) "auth.last_login_at"
     *         ["lastlogin"] => string(10) "1338329771"
     *         ["username"]  => string(4) "auser"
     *         ["email"]     => string(12) "auser@example.com"
     *         ["cust_name"] => string(4) "INEX"
     *         ["cust_id"]   => string(2) "15"
     *     }
     *
     *
     * @param int $limit Set this to limit the results to the last `$limit` users
     * @return array Users with their last login time ordered from most recent to oldest.
     */
    public function getLastLogins( $limit = null )
    {
        $q = $this->getEntityManager()->createQuery(
                "SELECT up.attribute AS attribute, up.value AS lastlogin, u.username AS username,
                        u.email AS email, c.name AS cust_name, c.id AS cust_id, u.id AS user_id
                    FROM \\Entities\\UserPreference up
                        JOIN up.User u
                        JOIN u.Customer c
                    WHERE up.attribute = ?1
                    ORDER BY up.value DESC"
            )
            ->setParameter( 1, 'auth.last_login_at' );
        
        if( $limit != null && is_numeric( $limit ) && $limit > 0 )
            $q->setMaxResults( $limit );
        
        return $q->getScalarResult();
    }

    /**
     * Return an array of users with their last login time ordered from most recent to oldest. (DQL)
     *
     * As an example, an element of the returned array contains:
     *
     *     [0] => array(6) {
     *         ["attribute"] => string(18) "auth.last_login_at"
     *         ["lastlogin"] => string(10) "1338329771"
     *         ["username"]  => string(4) "auser"
     *         ["email"]     => string(12) "auser@example.com"
     *         ["cust_name"] => string(4) "INEX"
     *         ["cust_id"]   => string(2) "15"
     *     }
     *
     * @param \stdClass $feParams
     *
     * @return array Users with their last login time ordered from most recent to oldest.
     */
    public function getLastLoginsForFeList( $feParams )
    {
        $dql = "SELECT  c2u.last_login_date AS last_login_date, 
                        u.username AS username,
                        u.email AS email, 
                        c.name AS cust_name, 
                        c.id AS cust_id, 
                        c2u.id AS c2u_id,
                        u.id AS id
                    FROM Entities\\CustomerToUser c2u
                        JOIN c2u.user u
                        JOIN c2u.customer c";


        if( isset( $feParams->listOrderBy ) ) {
            $dql .= " ORDER BY " . $feParams->listOrderBy . ' ';
            $dql .= isset( $feParams->listOrderByDir ) ? $feParams->listOrderByDir : 'ASC';
        }

        return $this->getEntityManager()->createQuery( $dql )->getArrayResult();
    }



    /**
     * Return an array of users subscribed (or not) to a given mailing list
     *
     * @param string $list The mailing list handle
     * @param bool $subscribed Set to false to get a list of users not subscribed
     * @param bool $withPassword Include passwords in the query
     * @return array Array of array of emails
     */
    public function getMailingListSubscribers( string $list, bool $subscribed = true, bool $withPassword = true ): array {
        $sql = "SELECT u.email AS email" . ( $withPassword ? ", u.password AS password" : "" ) . "
                    FROM \\Entities\\User u LEFT JOIN u.Preferences up
                    WHERE up.attribute = ?1 AND up.value = ?2
                    ORDER BY email ASC";
        
        return $this->getEntityManager()->createQuery( $sql )
            ->setParameter( 1, "mailinglist.{$list}.subscribed" )
            ->setParameter( 2, $subscribed )
            ->getScalarResult();
    }
    
    
    /**
     * Find all (active) users and arranged them in arrays by the privileges.
     *
     * Returns an array of the form:
     *
     *     [
     *         [3] => [
     *                    [0] => [
     *                               [username] => joe
     *                               [email] => joe@example.com
     *                               [password] => soopersecret
     *                               [privs] => 3
     *                               [custname] => SOME_IXP
     *                           ],
     *                    ...
     *                ],
     *         [2] => [
     *                    ...
     *                ],
     *         [1] => [
     *                    ...
     *                ]
     *     ]
     *
     * @return array As defined above
     */
    public function arrangeByType()
    {
        $users = $this->getEntityManager()->createQuery(
                "SELECT u.username AS username, u.email AS email, u.password AS password, u.privs AS privs,
                        c.name AS custname
                FROM \\Entities\\User u
                    Join u.Customer c
                WHERE
                    u.disabled = 0
                ORDER BY u.privs DESC, u.username ASC"
            )->getArrayResult();

        $arranged = [];
        foreach( $users as $u )
            $arranged[ $u['privs'] ][] = $u;
        
        return $arranged;
    }


    /**
     * Get all Users for listing on the frontend CRUD for a SuperUser
     *
     * @see \IXP\Http\Controllers\Doctrine2Frontend
     *
     *
     * @param \stdClass $feParams
     * @param int|null $id
     *
     * @param UserEntity|null $user
     *
     * Returns an array of the form:
     *
     *     [
     *         [0] => array:11 [
     *               [id] => 664
     *               [name]         => "Joe Doe"
     *               [username]     => "joe"
     *               [email]        => "joe@example.com"
     *               [created]      => DateTime @1550050069 {#1523 …1}
     *               [disabled]     => false
     *               [custid]       => 69
     *               [customer]     => "Customer Name"
     *               [lastupdated]  => DateTime @1553249875 {#1524 …1}
     *               [nbC2U]        => "1"
     *               [privileges]   => "1"
     *           ]
     *          [1] => [
     *                    ...
     *                ],
     *     ]
     *
     * @return array Array of User (as associated arrays) (or single element if `$id` passed)
     */
    public function getAllForFeListSuperUser( int $id = null )
    {
        $dql = "SELECT  u.id as id, 
                        u.name AS name,
                        u.username as username, 
                        u.email as email,
                        u.created as created, 
                        u.creator as creator, 
                        u.disabled as disabled,
                        u.peeringdb_id as peeringdb_id,
                        c.id as custid, 
                        c.name as customer,
                        u.lastupdated AS lastupdated,
                        COUNT( c2u ) as nbC2U,
                        MAX( c2u.privs ) as privileges,
                        ps.google2fa_enable as google2fa_enabled,
                        ps.id as psid 
                  FROM Entities\\User u
                        LEFT JOIN u.Customer as c
                        LEFT JOIN u.Customers as c2u
                        LEFT JOIN u.PasswordSecurity as ps
                  WHERE 1 = 1";

        if( $id ) {
            $dql .= " AND u.id = " . $id ;
        }

        $dql .= " GROUP BY id
                  ORDER BY username ASC";


        return $this->getEntityManager()->createQuery( $dql )->getArrayResult();

    }



    /**
     * Get all Users for listing on the frontend CRUD for a CustAdmin Restricted by The selected customer
     *
     * @see \IXP\Http\Controllers\Doctrine2Frontend
     *
     *
     * @param UserEntity $user
     * @param int|null $id
     *
     *
     * Returns an array of the form:
     *
     *     [
     *         [0] => array:11 [
     *               [id] => 664
     *               [name]         => "Joe Doe"
     *               [username]     => "joe"
     *               [email]        => "joe@example.com"
     *               [created]      => DateTime @1550050069 {#1523 …1}
     *               [disabled]     => false
     *               [custid]       => 69
     *               [customer]     => "Customer Name"
     *               [lastupdated]  => DateTime @1553249875 {#1524 …1}
     *               [nbC2U]        => "1"
     *               [privileges]   => "1"
     *           ]
     *          [1] => [
     *                    ...
     *                ],
     *     ]
     *
     * @return array Array of User (as associated arrays) (or single element if `$id` passed)
     */
    public function getAllForFeListCustAdmin( UserEntity $user, int $id = null )
    {
        $dql = "SELECT  u.id as id, 
                        u.name AS name,
                        u.username as username, 
                        u.email as email,
                        u.created as created, 
                        u.disabled as disabled, 
                        c.id as custid, 
                        c.name as customer,
                        u.peeringdb_id as peeringdb_id,
                        u.lastupdated AS lastupdated,
                        COUNT( c2u ) as nbC2U,
                        MAX( c2u.privs ) as privileges,
                        c2u.id as c2uid,
                        ps.google2fa_enable as google2fa_enabled,
                        ps.id as psid
                  FROM Entities\\User u
                  LEFT JOIN u.Customer as c 
                  LEFT JOIN u.Customers as c2u
                  LEFT JOIN u.PasswordSecurity as ps
                  WHERE 1 = 1
                  AND c2u.customer = " . $user->getCustomer()->getId() . "
                  AND c2u.privs <= " . UserEntity::AUTH_CUSTADMIN;

        if( $id ) {
            $dql .= " AND u.id = " . $id ;
        }

        $dql .= " GROUP BY id
                    ORDER BY username ASC";
       

        return $this->getEntityManager()->createQuery( $dql )->getArrayResult();

    }

    /**
     * Get the number of CustomerToUser Per User
     *
     * Returns an array of the form:
     *
     *     [
     *         [0] => array:2 [
     *               [id]       => 1
     *               [nbC2U]    => '2'
     *           ]
     *          [1] => [
     *               id]        => 1
     *               [nbC2U]    => '1'
     *          ],
     *     ]
     *
     * @return array Array of info
     */
    public function getNumberOfCustomers()
    {

        $dql = "SELECT u.id as id, 
                       COUNT( c2u ) as nbC2U
                       FROM Entities\\User u
                       LEFT JOIN u.Customers as c2u
                       GROUP BY id";

        $result = [];
        foreach( $this->getEntityManager()->createQuery( $dql )->getArrayResult() as $item ){
            $result[ $item[ 'id' ] ] = $item[ 'nbC2U' ];
        }

        return $result;

    }




    /**
     * Find users by username
     *
     * Will support a username starts / ends with as it uses LIKE
     *
     * @param  string $username The username to search for
     *
     * @return \Entities\User[] Matching users
     */
    public function findByUsername( $username ){
        return $this->getEntityManager()->createQuery(
            "SELECT u
                  FROM \\Entities\\User u
                  WHERE u.username LIKE :username"
        )
            ->setParameter( 'username', $username )
            ->getResult();
    }

    /**
     * Find Users by email
     *
     * @param  string $email The email to search for
     *
     * @return \Entities\User[] Matching users
     */
    public function findByEmail( $email )
    {
        return $this->getEntityManager()->createQuery(
            "SELECT u

                 FROM \\Entities\\User u
      

                 WHERE u.email = :email"
        )
            ->setParameter( 'email', $email )
            ->getResult();
    }


    /**
     * Find or create a user from PeeringDB information.
     *
     *
     *  +pdbuser: array:8 [▼
     *    "family_name" => "Bloggs"
     *    "email" => "ixpmanager@example.com"
     *    "name" => "Joe Bloggs"
     *    "verified_user" => true
     *    "verified_email" => true
     *    "networks" => array:2 [▼
     *      0 => array:4 [▼
     *        "perms" => 1
     *        "id" => 888
     *        "name" => "INEX Route Collectors"
     *        "asn" => 65501
     *      ]
     *      1 => array:4 [▼
     *        "perms" => 1
     *        "id" => 777
     *        "name" => "INEX Route Servers"
     *        "asn" => 65500
     *      ]
     *    ]
     *    "id" => 666
     *    "given_name" => "Joe"
     *  ]
     * }
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function findOrCreateFromPeeringDb( array $pdbuser )
    {
        // results to pass back:
        $result = [
            'user'         => null,
            'added_to'     => [],
            'removed_from' => [],
        ];

        // let's make sure we have a reason to do any work before we start:
        $asns = [];
        foreach( $pdbuser['networks'] as $nw ) {
            if( is_numeric($nw['asn']) && (int)$nw['asn'] > 0 ) {
                $asns[] = (int)$nw[ 'asn' ];
            }
        }

        if( !count( $asns ) ) {
            Log::info( 'PeeringDB OAuth: no valid affiliated networks for ' . $pdbuser['name'] . '/' . $pdbuser['email'] );
            return $result;
        }

        if( (int)D2EM::createQuery( "SELECT COUNT(c.autsys) FROM Entities\Customer c where c.autsys IN ( " . implode( ',', $asns ) . " )" )->getSingleScalarResult() == 0 ) {
            Log::info( 'PeeringDB OAuth: no customers for attempted login from ' . $pdbuser['name'] . '/' . $pdbuser['email'] . ' with networks: ' . implode( ',', $asns ) );
            return $result;
        }


        // what privilege do we use?
        $priv = isset( UserEntity::$PRIVILEGES_TEXT_NONSUPERUSER[ config( 'auth.peeringdb.privs' ) ] ) ? config( 'auth.peeringdb.privs' ) : UserEntity::AUTH_CUSTUSER;

        // if we don't have a user already, create one with unique username
        if( !( $user = $this->findOneBy( ['peeringdb_id' => $pdbuser['id'] ] ) ) ) {

            $un = strtolower( $pdbuser['name'] ?? 'unknownpdbuser' );
            $un = preg_replace( '/[^a-z0-9\._\-]/', '.', $un );

            $int = 0;

            do {
                $int++;
                $uname = $un . ( $int === 1 ? '' : "{$int}" );
            } while( $this->findOneBy([ 'username' => $uname ] ) );

            $user = new UserEntity();
            $user->setPeeringDbId( $pdbuser['id'] );
            $user->setUsername( $uname );
            $user->setPassword( Hash::make( Str::random() ) );
            $user->setPrivs( $priv );
            $user->setCreator( 'OAuth-PeeringDB' );
            $user->setCreated( now() );
            $user->setLastupdated( now() );
            D2EM::persist( $user );
            D2EM::flush( $user );

            $user_created = true;
            Log::info( 'PeeringDB OAuth: created new user ' . $user->getId() . '/' . $user->getUsername() . ' for PeeringDB user: ' . $pdbuser['name'] . '/' . $pdbuser['email'] );
        } else {
            $user_created = false;
            Log::info( 'PeeringDB OAuth: found existing user ' . $user->getId() . '/' . $user->getUsername() . ' for PeeringDB user: ' . $pdbuser['name'] . '/' . $pdbuser['email'] );
        }

        $user->setName( $pdbuser['name'] );
        $user->setEmail( $pdbuser['email'] );
        D2EM::flush();
        $result['user'] = $user;

        // user updated or created now.
        // we still need to link to customers

        // let's start with removing any customers that are no longer in the peeringdb networks list
        foreach( $user->getCustomers2User() as $c2u ) {
            $key = array_search( $c2u->getCustomer()->getAutsys(), $asns );

            if( $key === false || ( $key && !$c2u->getCustomer()->getPeeringdbOAuth() ) ) {
                // either user has a network that's not in the current peeringdb list of affiliated networks
                // or user has a network that (now) indicates PeeringDB OAuth should be disabled
                // then => if it came from peeringdb, remove it
                $ea = $c2u->getExtraAttributes();
                if( $ea && isset( $ea['created_by']['type'] ) && $ea['created_by']['type'] === 'PeeringDB' ) {
                    D2EM::getRepository( UserLoginHistoryEntity::class )->deleteUserLoginHistory( $c2u->getId() );

                    // if this is the user's default / last logged in as customer, reset it:
                    if( !$user->getCustomer() || $user->getCustomer()->getId() === $c2u->getCustomer()->getId() ) {
                        $user->setCustomer(null);
                    }

                    $result['removed_from'][] = $c2u->getCustomer();
                    Log::info( 'PeeringDB OAuth: removing user ' . $user->getId() . '/' . $user->getUsername() . ' from ' . $c2u->getCustomer()->getFormattedName() );
                    D2EM::remove( $c2u );
                    D2EM::flush();

                }
            } else {
                // we already have a link so take it out of the array
                Log::info( 'PeeringDB OAuth: user ' . $user->getId() . '/' . $user->getUsername() . ' already linked to AS' . $asns[ $key ] );
                unset( $asns[ $key ] );
            }
        }

        // what's left in $asns is potential new customers:
        foreach( $asns as $asn ) {
            /** @var \Entities\Customer $cust */
            if( $cust = D2EM::getRepository( 'Entities\Customer' )->findOneBy( ['autsys' => $asn ] ) ) {

                Log::info( 'PeeringDB OAuth: user ' . $user->getId() . '/' . $user->getUsername() . ' has PeeringDB affiliation with ' . $cust->getFormattedName() );

                // is this a valid customer?
                if( !( $cust->isTypeFull() || $cust->isTypeProBono() ) || !$cust->statusIsNormal() || $cust->hasLeft() || !$cust->getPeeringdbOAuth() ) {
                    Log::info( 'PeeringDB OAuth: ' . $cust->getFormattedName() . ' not a suitable IXP Manager customer for PeeringDB, skipping.' );
                    continue;
                }

                /** @var CustomerToUserEntity $c2u */
                $c2u = new CustomerToUserEntity;
                $c2u->setCustomer( $cust );
                $c2u->setUser( $user );
                $c2u->setPrivs( $priv );
                $c2u->setCreatedAt( now() );
                $c2u->setExtraAttributes( [ "created_by" => [ "type" => "PeeringDB"  ] ] );

                D2EM::persist( $c2u );
                D2EM::flush();

                $result['added_to'][] = $c2u->getCustomer();
                Log::info( 'PeeringDB OAuth: user ' . $user->getId() . '/' . $user->getUsername() . ' linked with with ' . $cust->getFormattedName() );
                
                if( $user_created ) {
                    // should not emit any more UserCreatedEvent events
                    $user_created = false;
                    event( new UserCreatedEvent( $user ) );
                } else {
                     event( new UserAddedToCustomerEvent( $c2u ) );
                }
            }
        }

        // refresh from database
        D2EM::refresh($user);

        // do we actually have any customers afterall this?
        if( !count( $user->getCustomers() ) ) {

            Log::info( 'PeeringDB OAuth: user ' . $user->getId() . '/' . $user->getUsername() . ' has no customers - deleting...' );

            // delete all the user's preferences
            foreach( $user->getPreferences() as $pref ) {
                $user->removePreference( $pref );
                D2EM::remove( $pref );
            }

            // delete all the user's API keys
            foreach( $user->getApiKeys() as $ak ) {
                $user->removeApiKey( $ak );
                D2EM::remove( $ak );
            }

            D2EM::remove( $user );
            $result['user'] = null;

        } else if( $user->getCustomer() === null ) {
            // set a default customer if we do not have one
            Log::info( 'PeeringDB OAuth: user ' . $user->getId() . '/' . $user->getUsername() . ' given default customer: ' . ($user->getCustomers()[0])->getFormattedName() );
            $user->setCustomer( $user->getCustomers()[0] );
        }
        D2EM::flush();

        return $result;
    }

    /**
     * Return a user depending a token and id
     *
     * @param $identifier
     * @param $token
     *
     * @return UserEntity|null
     *
     * @throws
     */
    public function retrieveByOtcToken( $identifier, $token )
    {
        $now = new DateTime();

        $sql = "SELECT ort
                FROM Entities\\OtpRememberTokens ort 
                WHERE ort.User = ?1
                AND ort.token = ?2
                AND ort.expires > ?3";

        $result = $this->getEntityManager()->createQuery( $sql )
            ->setParameter( '1', $identifier )
            ->setParameter( '2', $token )
            ->setParameter( '3', $now->format( 'Y-m-d H:i:s' ) )
            ->getOneOrNullResult();

        if( $result ) {
            return $result->getUser();
        }

        return null;

    }

    /**
     * Delete all the active session and remember me token for the user
     *
     * @param int   $id
     * @param bool  $deleteCurrentSession Do we need to delete the current session
     *
     * @return void
     */
    public function deleteActiveSession( int $id, $deleteCurrentSession = false ) {

        D2EM::getRepository( UserRememberTokensEntity::class    )->deleteByUser( $id, $deleteCurrentSession );
        D2EM::getRepository( SessionEntity::class               )->deleteByUser( $id, $deleteCurrentSession );

    }
}
