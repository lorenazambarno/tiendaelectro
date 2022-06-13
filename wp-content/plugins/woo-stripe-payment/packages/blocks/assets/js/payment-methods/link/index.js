import {useEffect, useState} from '@wordpress/element';
import {Elements, useStripe, useElements, PaymentElement} from "@stripe/react-stripe-js";
import {registerExpressPaymentMethod} from '@woocommerce/blocks-registry';
import {useLink} from './hooks';
import {getSettings, initStripe as loadStripe, canMakePayment} from "../util";

const getData = getSettings('stripe_link_checkout_data');

export const LinkComponent = ({cartData, ...props}) => {
    const clientSecret = cartData?.extensions?.stripe_link_checkout?.clientSecret;
    if (clientSecret) {
        return (
            <Elements stripe={loadStripe} options={{clientSecret}}>
                <LinkCheckout {...props}/>
            </Elements>
        );
    }
    return null;
}

const LinkCheckout = (
    {
        billing,
        shipping,
        eventRegistration,
        onClick,
        onSubmit,
        activePaymentMethod,
        emitResponse,
        ...props
    }) => {
    const {billingData, currency} = billing;
    const {responseTypes, noticeContexts} = emitResponse;
    const {email} = billingData;
    const isActive = activePaymentMethod === getData('name');
    useLink({
        email,
        eventRegistration,
        onClick,
        onSubmit,
        isActive,
        responseTypes
    });
    return <div style={{display: 'none'}}><PaymentElement/></div>;
}

registerExpressPaymentMethod({
    name: getData('name'),
    canMakePayment: (props) => {
        return true;
    },
    content: <LinkComponent/>,
    edit: <LinkComponent/>,
    supports: {
        showSavedCards: getData('showSavedCards'),
        showSaveOption: getData('showSaveOption'),
        features: getData('features')
    }
})