import {useEffect, useState, useRef} from '@wordpress/element';
import {useStripe, useElements} from "@stripe/react-stripe-js";
import {toCartAddress as mapToCartAddress, ensureSuccessResponse, getBillingDetailsFromAddress} from '../../util';

const toCartAddress = mapToCartAddress();

export const useLink = (
    {
        email,
        eventRegistration,
        onClick,
        onSubmit,
        isActive,
        responseTypes,
        ...props
    }) => {
    const [link, setLink] = useState();
    const stripe = useStripe();
    const elements = useElements();
    const currentData = useRef();
    const linkData = useRef();
    const {onPaymentProcessing} = eventRegistration;
    useEffect(() => {
        currentData.current = {onClick, onSubmit}
    });

    useEffect(() => {
        if (stripe && elements) {
            setLink(stripe?.linkAutofillModal(elements));
        }
    }, [stripe, elements]);

    useEffect(() => {
        if (link) {
            link.launch({email});
        }
    }, [link, email]);

    useEffect(() => {
        if (link) {
            link.on('autofill', event => {
                linkData.current = event;
                currentData.current.onSubmit();

            });
            link.on('authenticated', event => {
                currentData.current.onClick();
            })
        }
    }, [link]);

    useEffect(() => {
        if (isActive) {
            const unsubscribe = onPaymentProcessing(async () => {
                const response = {meta: {}};
                const {shippingAddress = null, billingAddress = null} = linkData.current.value;
                if (billingAddress) {
                    response.meta.billingData = toCartAddress({...billingAddress.address, recipient: billingAddress.name})
                }
                if (shippingAddress) {
                    response.meta.shippingData = toCartAddress({...shippingAddress.address, recipient: shippingAddress.name})
                }
                // update the payment intent
                try {
                    const result = await stripe.updatePaymentIntent({
                        elements,
                        params: {
                            payment_method_data: {
                                billing_details: getBillingDetailsFromAddress(response.meta.billingData)
                            }
                        }
                    });
                    response.meta.paymentMethodData = {
                        stripe_cc_token_key: result.paymentIntent.payment_method,
                        stripe_cc_save_source_key: false,
                    }
                } catch (error) {
                    console.log(error);
                }
                return ensureSuccessResponse(responseTypes, response);
            });

            return () => unsubscribe();
        }
    }, [isActive, onPaymentProcessing, stripe, elements])

    return link;
}