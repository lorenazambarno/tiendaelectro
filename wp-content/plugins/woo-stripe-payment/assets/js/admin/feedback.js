import {useState, useCallback, useEffect, render} from '@wordpress/element';
import {Modal, Button, RadioControl, TextareaControl} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

window.addEventListener('load', () => {
    const app = document.createElement('div');
    app.id = 'stripe-app';
    document.getElementById('wpbody')?.append(app);
    const el = document.getElementById('deactivate-woo-stripe-payment');
    render(<App el={el}/>, document.getElementById('stripe-app'));
});

const App = ({el}) => {
    const [open, setOpen] = useState(false);
    const openModal = useCallback((e) => {
        e.preventDefault();
        setOpen(true)
    }, []);
    useEffect(() => {
        el.addEventListener('click', openModal);
        return () => el.removeEventListener('click', openModal);
    }, [openModal]);
    const submit = () => {
        el.removeEventListener('click', openModal);
        el.click();
        setOpen(false);
    }
    return <FeedbackModal submit={submit} deactivateLink={el.href} open={open} setOpen={setOpen} data={stripeFeedbackParams}/>
}

const FeedbackModal = ({deactivateLink, open, setOpen, data, submit}) => {
    const [reasonCode, setReasonCode] = useState(false);
    const [reasonText, setReasonText] = useState('');
    const [processing, setProcessing] = useState();
    const [placeholder, setPlaceHolder] = useState('');
    const onClose = () => setOpen(false);
    const options = Object.keys(data.options).map(id => ({
        label: data.options[id],
        value: id
    }));
    const onSubmit = async () => {
        setProcessing(true);
        try {
            await apiFetch({
                method: 'POST',
                url: data.route,
                data: {
                    reason_code: reasonCode,
                    reason_text: reasonText
                }
            })
        } catch (error) {

        } finally {
            setProcessing(false);
            submit();
        }
    }

    useEffect(() => {
        if (data.placeholders.hasOwnProperty(reasonCode)) {
            setPlaceHolder(data.placeholders[reasonCode]);
        } else {
            setPlaceHolder('');
        }
    }, [reasonCode]);

    const props = {
        title: data.title,
        isDismissible: true,
        focusOnMount: true,
        isFullScreen: false,
        onRequestClose: onClose
    }
    if (open) {
        return (
            <Modal {...props}>
                <div className='stripe-modal-content'>
                    <p>{data.description}</p>
                    <div className='options-container'>
                        <RadioControl selected={reasonCode} options={options} onChange={setReasonCode}/>
                    </div>
                    <div className='stripe-deactivation__text'>
                        <TextareaControl placeholder={placeholder} label={data.reasonTextLabel} value={reasonText} onChange={setReasonText}/>
                    </div>
                </div>
                <div className='stripe-modal-actions'>
                    <Button variant='primary' onClick={onSubmit} isBusy={processing} disabled={processing}>{data.buttons.primary}</Button>
                    <Button href={deactivateLink} className='stripe-skip-deactivate' variant='tertiary' onClick={onClose}>{data.buttons.secondary}</Button>
                </div>
            </Modal>
        )
    }
    return null;
}