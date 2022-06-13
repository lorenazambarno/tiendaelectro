import {useEffect, useState, useCallback, useMemo} from '@wordpress/element';
import {Elements, PaymentElement, useStripe, useElements} from '@stripe/react-stripe-js';
import {
    getSettings,
    initStripe as loadStripe
} from '../util';

import {Installments} from '../../components/checkout/';

import {useProcessCheckoutError, useUpdatePaymentIntent} from "../hooks";


const getData = getSettings('stripe_cc_data');

export const PaymentElementComponent = ({cartData, ...props}) => {
    const clientSecret = cartData?.extensions?.stripe_cc?.clientSecret;
    return (
        <>
            <Elements stripe={loadStripe} options={{...getData('elementOptions'), clientSecret}}>
                <CardElement {...props} clientSecret={clientSecret}/>
            </Elements>
        </>
    );
}

const CardElement = ({onComplete, clientSecret, ...props}) => {
    const [formComplete, setFormComplete] = useState(false);
    const installmentsActive = getData('installmentsActive')
    const elements = useElements();
    const stripe = useStripe();
    const {billing: {billingData}, eventRegistration, emitResponse, shouldSavePayment} = props;
    const {onPaymentProcessing, onCheckoutAfterProcessingWithError} = eventRegistration;
    const {responseTypes, noticeContexts} = emitResponse;
    const name = getData('name');
    const onChange = useCallback((event) => {
        setFormComplete(event.complete);
    }, []);
    const {updatePaymentIntent, addPaymentMethodData} = useUpdatePaymentIntent({
        clientSecret,
        billingData,
        eventRegistration,
        responseTypes,
        shouldSavePayment,
        noticeContexts,
        name
    });

    useProcessCheckoutError({
        responseTypes,
        subscriber: onCheckoutAfterProcessingWithError,
        messageContext: noticeContexts.PAYMENTS
    });

    const getPaymentMethod = useCallback(async () => {
        let paymentMethod = null;
        const result = await updatePaymentIntent();
        if (result?.paymentIntent?.payment_method) {
            paymentMethod = result.paymentIntent.payment_method;
        }
        return paymentMethod;
    }, [updatePaymentIntent]);

    const options = {
        fields: {
            billingDetails: 'never'
        },
        wallets: {applePay: 'never', googlePay: 'never'}
    }
    return (
        <>
            <PaymentElement options={options} onChange={onChange}/>
            {installmentsActive && <Installments
                paymentMethodName={getData('name')}
                stripe={stripe}
                cardFormComplete={formComplete}
                getPaymentMethod={getPaymentMethod}
                addPaymentMethodData={addPaymentMethodData}/>}
        </>
    )
}

export default PaymentElementComponent;