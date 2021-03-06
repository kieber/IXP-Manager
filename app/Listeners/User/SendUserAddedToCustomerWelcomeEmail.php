<?php

namespace IXP\Listeners\User;

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

use Mail;

use IXP\Events\User\UserAddedToCustomer as UserAddedToCustomerEvent;

use IXP\Mail\User\UserAddedToCustomer as UserAddedToCustomerMailable;

class SendUserAddedToCustomerWelcomeEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  UserAddedToCustomerEvent  $e
     * @return void
     */
    public function handle( UserAddedToCustomerEvent $e )
    {
        Mail::to( $e->c2u->getUser()->getEmail() )->send( new UserAddedToCustomerMailable( $e->c2u ) );
    }
}