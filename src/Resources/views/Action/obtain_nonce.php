<?php

/**
 * @var string $merchant_reference
 * @var string $amount
 * @var float $numeric_amount
 * @var string $currencyCode
 * @var string $actionUrl
 * @var mixed $captureContext
 * @var string contextData
 * @var string $imgUrl
 */
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        <?= file_get_contents(__DIR__ . '/style.css') ?>
    </style>
</head>

<body class="flex justify-center align-middle h-full">
    <form id="payment-form" action="<?= $actionUrl ?>" method="post" class="w-[80vw] sm:w-[30vw] self-center shadow-lg rounded-lg p-8">
        <?php if ($imgUrl) { ?>
            <img src="<?= $imgUrl ?>" class="max-w-full h-auto" />
        <?php } ?>
        <div id="card-container" class="mt-2">
            <div class="form-group">
                <label for="cardholderName">Name</label>
                <input id="cardholderName" class="form-control" name="cardholderName" placeholder="Name on the card">
                <label id="cardNumber-label">Card Number</label>
                <div id="number-container" class="form-control"></div>
                <label for="securityCode-container">Security Code</label>
                <div id="securityCode-container" class="form-control"></div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="expMonth">Expiry month</label>
                    <select id="expMonth" class="form-control">
                        <?php for ($month = 1; $month < 13; $month++) { ?>
                            <option><?= str_pad($month, 2, '0', STR_PAD_LEFT) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="expYear">Expiry year</label>
                    <select id="expYear" class="form-control">
                        <?php for ($year = 0; $year < 10; $year++) { ?>
                            <option><?= date('Y') + $year ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <input type="hidden" id="flexresponse" name="flexresponse">
        </div>


        <button id="card-button" type="button" class="invisible">Pay <?= $amount ?></button>
        <div class="text-center mt-2">
            <small>
                Powered by
            </small>
            <br>

            <div class="max-w-3/4 mx-auto">
                <svg xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="logo-title" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 150 35">
                    <defs>
                        <style>
                            .a {
                                isolation: isolate;
                            }

                            .b {
                                fill: #3874fd;
                            }

                            .c {
                                fill: none;
                                stroke: #202a44;
                                stroke-linecap: round;
                            }

                            .d {
                                fill: #202a44;
                            }
                        </style>
                    </defs>
                    <title id="logo-title">Developer Cybersource Logo</title>
                    <a id="cybsdeveloper-logo-area" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="/" aria-label="Cybersource Developer home page">
                        <rect xmlns="http://www.w3.org/2000/svg" x="161" y="0" width="108.5" height="35" fill="transparent"></rect>
                    </a>
                    <a id="cybs-logo-area" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="https://www.cybersource.com/" aria-label="home page cybersource visa solution">
                        <rect xmlns="http://www.w3.org/2000/svg" x="0" y="0" width="161.2" height="35" fill="transparent"></rect>
                    </a>
                    <a id="cybs-logo" xlink:href="https://www.cybersource.com/" aria-label="home page cybersource visa solution">
                        <path class="d" d="M12.9,8.6c0,.1.1.2.1.4a.8.8,0,0,1-.4.6l-2.2,1.5H9.9c-.3,0-.5-.3-.6-.5A2.7,2.7,0,0,0,7.1,9.5a2.6,2.6,0,0,0-2.7,2.6A2.6,2.6,0,0,0,7,14.7a2.8,2.8,0,0,0,2.3-1.3c.1-.1.3-.4.6-.4h.5l2.2,1.5a.7.7,0,0,1,.4.6c0,.1-.1.3-.1.4-1,2-3.6,3.2-6.1,3.2A6.6,6.6,0,0,1,0,12.5v-.3A6.7,6.7,0,0,1,6.7,5.4h.2c2.5,0,5,1.1,6,3.2" transform="translate(0)"></path>
                        <path class="d" d="M23.4,20.7a5.4,5.4,0,0,1-2.3,3.4,6.3,6.3,0,0,1-3.3.9,5.9,5.9,0,0,1-2.9-.7,2.2,2.2,0,0,1-.9-.7.5.5,0,0,1-.3-.5,1.4,1.4,0,0,1,.2-.6l1.4-2a.5.5,0,0,1,.5-.3l.7.2a2.5,2.5,0,0,0,1.4.6,1.5,1.5,0,0,0,1.5-1l.5-1.6H17.5a.7.7,0,0,1-.7-.6L13.5,6.7a.7.7,0,0,1-.1-.5.5.5,0,0,1,.5-.5h3.8c.4,0,.6.3.6.7l2.1,8.7h.4L23,6.4c0-.3.3-.7.6-.7h3.5a.6.6,0,0,1,.6.5,4.3,4.3,0,0,1-.1.5Z" transform="translate(0)"></path>
                        <path class="d" d="M35.5,14.7a2.7,2.7,0,0,0,2.7-2.6,2.7,2.7,0,0,0-5.3-.1h0a2.6,2.6,0,0,0,2.6,2.6h0M32.9,6.6a4.2,4.2,0,0,1,3.4-1.2A6.2,6.2,0,0,1,42.5,12c0,4.2-2.9,6.8-6.2,6.8a4.2,4.2,0,0,1-3.4-1.2v.2a.5.5,0,0,1-.6.6H29.1a.6.6,0,0,1-.6-.5h0V1.1a.6.6,0,0,1,.6-.6h3.2a.6.6,0,0,1,.6.6Z" transform="translate(0)"></path>
                        <path class="d" d="M47.8,10.6H52a2.1,2.1,0,0,0-2.7-1.5,2.3,2.3,0,0,0-1.5,1.5m4.6,3.2a.9.9,0,0,1,.7-.5h.4l2.2,1.4a.7.7,0,0,1,.4.6.8.8,0,0,1-.1.4c-.9,1.9-3.4,3-5.9,3a6.4,6.4,0,0,1-6.8-6.1v-.5a6.5,6.5,0,0,1,6.4-6.8H50a6.1,6.1,0,0,1,6.3,6.1v.7c0,.4-.1.8-.7.8H47.7a2.2,2.2,0,0,0,2.4,2,2.7,2.7,0,0,0,2.3-1.2" transform="translate(0)"></path>
                        <path class="d" d="M62,17.9a.6.6,0,0,1-.6.5H58.1a.6.6,0,0,1-.6-.5h0V6.3c0-.3.3-.5.6-.6h3.1c.3,0,.5.3.6.6V7a3.7,3.7,0,0,1,3.1-1.5h.8c.2,0,.6.1.6.8V9.4a.6.6,0,0,1-.6.8H64.3c-1.7,0-2.4.5-2.4,1.9Z" transform="translate(0)"></path>
                        <path class="d" d="M73.2,5.4a7.8,7.8,0,0,1,4.9,1.4c.3.3.4.4.4.6a.8.8,0,0,1-.2.5L77.2,9.7c0,.2-.2.3-.4.3a1.6,1.6,0,0,1-.9-.3,6.1,6.1,0,0,0-3-.8c-.7,0-1.4.2-1.4.8s.1.5.6.7,6.7.1,6.7,4.1c0,2.4-1.8,4.3-5.9,4.3a9.3,9.3,0,0,1-5.6-1.5,1,1,0,0,1-.4-.8.7.7,0,0,1,.3-.7l1.2-1.6c.1-.2.2-.2.4-.2s.6.3,1.2.6a6,6,0,0,0,2.8.8c.9,0,1.6-.2,1.6-.7s-7.1.1-7.1-4.8c0-2.8,2.3-4.5,5.9-4.5" transform="translate(0)"></path>
                        <path class="d" d="M89.1,12.1a2.6,2.6,0,0,0-2.6-2.5A2.5,2.5,0,0,0,84,12.2a2.5,2.5,0,0,0,2.5,2.5,2.6,2.6,0,0,0,2.6-2.6h0m4.5,0a7.1,7.1,0,1,1-7.3-6.7h.2A6.8,6.8,0,0,1,93.6,12h0" transform="translate(0)"></path>
                        <path class="d" d="M99,12.4c0,1.6.7,2.6,2.3,2.6a2.2,2.2,0,0,0,2.4-2V6.3a.6.6,0,0,1,.6-.6h3.2a.6.6,0,0,1,.6.6h0V17.8a.6.6,0,0,1-.5.6h-3.3a.6.6,0,0,1-.6-.5v-.5a4.3,4.3,0,0,1-3.6,1.4c-3.5,0-5.5-2.3-5.5-5.7V6.3a.6.6,0,0,1,.6-.6h3.2a.6.6,0,0,1,.6.6Z" transform="translate(0)"></path>
                        <path class="d" d="M114.1,17.9c0,.3-.2.5-.6.5h-3.2a.6.6,0,0,1-.6-.5h0V6.3c0-.3.3-.5.6-.6h3a.6.6,0,0,1,.6.6V7a4.1,4.1,0,0,1,3.2-1.5h.7c.3,0,.7.1.7.8V9.4a.6.6,0,0,1-.6.8h-1.4c-1.7,0-2.4.5-2.4,1.9Z" transform="translate(0)"></path>
                        <path class="d" d="M131.8,8.6a.5.5,0,0,1,.2.4c-.1.2-.2.5-.4.6l-2.2,1.5h-.5c-.3,0-.5-.3-.7-.5A2.6,2.6,0,0,0,126,9.5a2.6,2.6,0,1,0,0,5.2,2.5,2.5,0,0,0,2.2-1.3c.2-.1.4-.4.7-.4h.5l2.2,1.5a.9.9,0,0,1,.4.6.8.8,0,0,1-.2.4c-.9,2-3.5,3.2-6,3.2a6.5,6.5,0,0,1-6.8-6.3v-.3a6.7,6.7,0,0,1,6.7-6.8h.2c2.5,0,5,1.1,5.9,3.2" transform="translate(0)"></path>
                        <path class="d" d="M137,10.6h4.2a2.1,2.1,0,0,0-2.7-1.5,2.3,2.3,0,0,0-1.5,1.5m4.5,3.2c.2-.2.4-.5.7-.5h.5l2.1,1.4c.3.1.4.3.5.6s-.1.2-.2.4c-.9,1.9-3.3,3-5.9,3a6.5,6.5,0,0,1-6.8-6.1v-.5a6.6,6.6,0,0,1,6.4-6.8h.3a6.2,6.2,0,0,1,6.4,6.1v.7c0,.4-.1.8-.7.8h-7.9a2.2,2.2,0,0,0,2.4,2,2.8,2.8,0,0,0,2.3-1.2" transform="translate(0)"></path>
                        <path class="d" d="M74.7,33.8c0,.1-.1.1-.2.1H73.2L70.1,25a.1.1,0,0,1,.1-.1h1.2L74,32.6,76.6,25a.1.1,0,0,1,.1-.1h1a.1.1,0,0,1,.1.1Z" transform="translate(0)"></path>
                        <path class="d" d="M79.1,27.6c0-.1,0-.1.1-.1H80a.1.1,0,0,1,.1.1v6.2a.1.1,0,0,1-.1.1h-.8c-.1,0-.1,0-.1-.1ZM78.9,25a.1.1,0,0,1,.1-.1h1.3v1.2H79a.1.1,0,0,1-.1-.1Z" transform="translate(0)"></path>
                        <path class="d" d="M84.1,34c-1.5,0-2.7-.9-2.7-2.1h1.1c.1.8.7,1.2,1.6,1.2s1.5-.3,1.5-1-.6-.9-1.9-1.1-2.2-.7-2.2-1.8,1.1-1.9,2.5-1.9a2.2,2.2,0,0,1,2.4,1.9c.1.1,0,.1,0,.1h-.8c-.1,0-.1,0-.1-.1a1.5,1.5,0,0,0-1.6-.9c-.8,0-1.4.3-1.4.8s.5.9,1.9,1.1,2.3.6,2.3,1.8-1.1,2-2.6,2" transform="translate(0)"></path>
                        <path class="d" d="M90.5,30.8c-.9,0-1.6.3-1.6,1.1s.5,1.2,1.4,1.2a2,2,0,0,0,2-1.8v-.2a5.7,5.7,0,0,0-1.8-.3m-2.3-1.4h-.1a2.5,2.5,0,0,1,2.6-1.9,2.4,2.4,0,0,1,2.6,2.1v3.3a1.4,1.4,0,0,0,.2,1.1H92.4c-.1,0-.1,0-.1-.1v-.7a2.8,2.8,0,0,1-2.1,1,2.1,2.1,0,0,1-2.3-2h0c0-1.4,1.1-2.1,2.6-2.1l1.8.2v-.3A1.3,1.3,0,0,0,91,28.3h-.3a1.5,1.5,0,0,0-1.5,1h-1Z" transform="translate(0)"></path>
                        <path class="d" d="M101.2,24.8c2,0,3.1,1.2,3.1,2.5h-1.1c0-.9-.9-1.5-2-1.5s-1.9.5-1.9,1.3,1,1.4,2.4,1.7,2.9,1.1,2.9,2.5-1.4,2.7-3.3,2.7S98,32.9,98,31.2c0-.1,0-.2.1-.2h.8a.2.2,0,0,1,.2.2,1.9,1.9,0,0,0,2.1,1.8h.1c1.4,0,2.2-.5,2.2-1.7s-1.1-1.2-2.3-1.5-3-1.1-3-2.6,1.4-2.4,3-2.4" transform="translate(0)"></path>
                        <path class="d" d="M109,33.1a2.4,2.4,0,0,0,2.6-2.2,2.4,2.4,0,0,0-2.2-2.6,2.4,2.4,0,0,0-2.6,2.2v.2a2.2,2.2,0,0,0,2,2.4h.2m0-5.8a3.4,3.4,0,0,1,3.4,3.3,3.2,3.2,0,0,1-3.3,3.4,3.3,3.3,0,0,1-3.4-3.3h0a3.2,3.2,0,0,1,3-3.4h.3" transform="translate(0)"></path>
                        <rect class="d" x="113.7" y="24.9" width="1" height="8.99" rx="0.4"></rect>
                        <path class="d" d="M121.7,33.8c0,.1,0,.1-.1.1h-.7c-.1,0-.2,0-.2-.1V33a2.5,2.5,0,0,1-2.1,1,2,2,0,0,1-2.2-1.9c-.1-.2,0-.3,0-.4V27.6c0-.1,0-.1.1-.1h.7c.1,0,.2,0,.2.1v3.9a1.4,1.4,0,0,0,1.1,1.6h.3c1.1,0,1.9-.8,1.9-2.4V27.6c0-.1.1-.1.2-.1h.7c.1,0,.1,0,.1.1Z" transform="translate(0)"></path>
                        <path class="d" d="M127.6,27.5c.1,0,.1,0,.1.1v.7h-2.3V32a1,1,0,0,0,1.1,1.1l1.2-.2h.1a.1.1,0,0,1,.1.1v.6c0,.1,0,.2-.1.2a2.4,2.4,0,0,1-1.4.2,1.8,1.8,0,0,1-2-1.6v-4h-1.2v-.7c0-.1,0-.1.1-.1h1.1V25.6c0-.1.1-.1.2-.1h.7c.1,0,.1,0,.1.1v1.9Z" transform="translate(0)"></path>
                        <path class="d" d="M129.3,27.6c0-.1.1-.1.2-.1h.7c.1,0,.1,0,.1.1v6.2c0,.1,0,.1-.1.1h-.7c-.1,0-.2,0-.2-.1Zm-.1-2.6a.1.1,0,0,1,.1-.1h1.1a.1.1,0,0,1,.1.1v1.2a.1.1,0,0,1-.1.1h-1.1a.1.1,0,0,1-.1-.1Z" transform="translate(0)"></path>
                        <path class="d" d="M135,33.1a2.4,2.4,0,0,0,2.6-2.2,2.4,2.4,0,0,0-2.2-2.6,2.4,2.4,0,0,0-2.6,2.2v.2a2.2,2.2,0,0,0,2,2.4h.2m0-5.8a3.4,3.4,0,0,1,3.4,3.3,3.2,3.2,0,0,1-3.3,3.4,3.3,3.3,0,0,1-3.4-3.3h0a3.2,3.2,0,0,1,3-3.4h.3" transform="translate(0)"></path>
                        <path class="d" d="M139.6,27.6c0-.1,0-.1.1-.1h.7c.1,0,.2,0,.2.1v.7a2.8,2.8,0,0,1,2.1-1,2.1,2.1,0,0,1,2.2,2c.1.1,0,.2,0,.4v4.1c0,.1,0,.1-.1.1h-.7c-.1,0-.2,0-.2-.1v-4a1.3,1.3,0,0,0-1.1-1.5h-.3c-1.1,0-1.9.8-1.9,2.3v3.2c0,.1-.1.1-.2.1h-.7c-.1,0-.1,0-.1-.1Z" transform="translate(0)"></path>
                        <path class="d" d="M61.9,30.3h2.9l-1.4-4.1Zm3.3,1H61.5l-.9,2.5c0,.1,0,.1-.1.1h-.9c-.1,0-.1,0-.1-.1h0L62.7,25a.1.1,0,0,1,.1-.1h1.1a.1.1,0,0,1,.1.1l3.2,8.7h0c0,.1,0,.1-.1.1h-.9c-.1,0-.1,0-.1-.1Z" transform="translate(0)"></path>
                    </a>
                </svg>
            </div>
        </div>
    </form>
    <script
        type="text/javascript"
        src="<?= $captureContext->clientLibrary ?>"
        integrity="<?= $captureContext->clientLibraryIntegrity ?>"
        crossorigin="anonymous"></script>
    <script>
        const form = document.querySelector('#payment-form')
        const payButton = document.querySelector('#card-button')
        const flexResponse = document.querySelector('#flexresponse')
        const expMonth = document.querySelector('#expMonth')
        const expYear = document.querySelector('#expYear')
        const errorsOutput = document.querySelector('#payment-status-container')

        const flex = new Flex('<?= $contextData ?>')

        const myStyles = {
            'input': {
                'font-size': '1em',
                'color': '#555',
            },
            ':focus': {
                color: 'blue',
            },
            ':disabled': {
                cursor: 'not-allowed',
            },
            'valid': {
                color: '#3c763d',
            },
            'invalid': {
                color: '#a94442',
            },
        }

        const microform = flex.microform('card', {
            styles: myStyles,
        })

        const cardNumber = microform.createField('number', {
            placeholder: 'Enter card number',
        })
        const securityCode = microform.createField('securityCode', {
            placeholder: '•••',
        })

        cardNumber.load('#number-container')
        securityCode.load('#securityCode-container')

        // Show Pay button
        payButton.classList.remove('invisible')

        // Configuring a Listener for the Pay button
        payButton.addEventListener('click', function() {
            // Compiling MM & YY into optional parameters
            const options = {
                expirationMonth: document.querySelector('#expMonth').value,
                expirationYear: document.querySelector('#expYear').value,
            }

            microform.createToken(options, function(err, token) {
                if (err) {
                    // handle error
                    console.error(err)
                    errorsOutput.textContent = err.message
                } else {
                    // At this point you may pass the token back to your server as you wish.
                    // In this example we append a hidden input to the form and submit it.
                    console.log(JSON.stringify(token))
                    flexResponse.value = JSON.stringify(token)
                    form.submit()
                }
            })
        })
    </script>
</body>

</html>