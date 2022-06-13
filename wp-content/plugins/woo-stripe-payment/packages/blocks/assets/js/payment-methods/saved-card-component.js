import {useEffect, useCallback} from '@wordpress/element';
import {handleCardAction} from '@paymentplugins/stripe/util';

const SavedCardComponent = (
    {
        eventRegistration,
        emitResponse,
        getData,
        method = 'handleCardAction'
    }) => {
    const {onCheckoutAfterProcessingWithSuccess} = eventRegistration;
    const {responseTypes} = emitResponse;
    const handleSuccessResult = useCallback(async ({redirectUrl}) => {
        return await handleCardAction({redirectUrl, getData, responseTypes, method});
    }, []);

    useEffect(() => {
        const unsubscribe = onCheckoutAfterProcessingWithSuccess(handleSuccessResult);
        return () => unsubscribe();
    }, [onCheckoutAfterProcessingWithSuccess, handleSuccessResult]);
    return null;
}

export default SavedCardComponent;
