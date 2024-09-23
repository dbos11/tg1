        function navigate(page) {
            window.location.href = /telegram/${page};
        }

        document.addEventListener('DOMContentLoaded', async function() {
            if (window.Telegram && window.Telegram.WebApp) {
                window.Telegram.WebApp.ready();
            } else {
                console.error("Telegram Web App не инициализирован");
                return;
            }

            const tonConnectUI = new TON_CONNECT_UI.TonConnectUI({
                manifestUrl: 'https://41363c7867a2.ngrok.app/telegram/tonconnect-manifest.json',
                buttonRootId: 'connect-button'
            });

            tonConnectUI.uiOptions = {
                language: 'eng',
                width: '100%',
                uiPreferences: {
                    connectButton: {
                        background: 'linear-gradient(90deg, #28B4C6, #1591A1)'
                    }
                }
            };

            try {
                const walletsList = await tonConnectUI.getWallets();
                console.log('Available wallets:', walletsList);
            } catch (error) {
                console.error('Error fetching wallets:', error);
            }

            const unsubscribe = tonConnectUI.onStatusChange(wallet => {
                if (wallet) {
                    console.log('Wallet connected:', wallet);
                    handleWalletConnected(wallet);
                } else {
                    console.log('Wallet disconnected');
                    handleWalletDisconnected();
                }
            });

            function handleWalletConnected(wallet) {
                console.log('Connected wallet object:', wallet);
                let wallet_hash = wallet.account ? wallet.account.address : 'Address not found';
                console.log('Connected wallet hash:', wallet_hash);

                let wallet_address;
                try {
                    const tonweb = new TonWeb();
                    const walletAddress = new tonweb.utils.Address(wallet_hash);
                    wallet_address = walletAddress.toString(true, true, true);
                    console.log('Connected wallet address:', wallet_address);
                } catch (e) {
                    console.error('Error converting address:', e);
                    wallet_address = 'Conversion error';
                }

                saveWalletToDB(wallet_address, wallet_hash);
            }

            function handleWalletDisconnected() {
                console.log('Wallet has been disconnected');
            }

            function saveWalletToDB(wallet_address, wallet_hash) {
                const telegramId = Telegram.WebApp.initDataUnsafe.user.id;
                const formData = new FormData();
                formData.append('telegram_id', telegramId);
                formData.append('wallet_address', wallet_address);
                formData.append('wallet_hash', wallet_hash);

                fetch('save_wallet.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Wallet saved successfully');
                    } else {
                        console.error('Error saving wallet:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error saving wallet:', error);
                });
            }

            // Styling the connect button
            function styleConnectButton() {
                const connectButton = document.querySelector('#connect-button button');
                if (connectButton) {
                    connectButton.style.display = 'flex';
                    connectButton.style.justifyContent = 'center';
                    connectButton.style.alignItems = 'center';
                    connectButton.style.flexDirection = 'row';
                    connectButton.style.width = '300px';
                    connectButton.style.height = '48px';
                    connectButton.style.background = 'linear-gradient(90deg, #109CFC, #0A5D96)';
                    connectButton.style.border = 'none';
                    connectButton.style.borderRadius = '8px';
                    connectButton.style.padding = '10.5px 0';
                    connectButton.style.boxSizing = 'border-box';
                    connectButton.style.marginTop = '5%';
                    connectButton.style.transition = 'transform 0.3s ease';
                    connectButton.style.userSelect = 'none';
                    connectButton.style.fontFamily = "'Inter', sans-serif";
                    connectButton.style.fontWeight = '500';
                    connectButton.style.fontSize = '16px';
                    connectButton.style.color = '#FFFFFF';
                    connectButton.textContent = 'Connect your Wallet'; // Change button text

                    // Adding an icon
                    const icon = document.createElement('img');
                    icon.src = 'си/assets/vectors/icon_1_x2.svg'; // Change this to your icon path
                    icon.style.marginRight = '8px';
                    icon.style.width = '27px';
                    icon.style.height = '27px';
                    connectButton.insertBefore(icon, connectButton.firstChild);
                } else {
                    console.error("Connect button not found");
                }
            }

            // Call the style function after a slight delay to ensure the button is rendered
            setTimeout(styleConnectButton, 0);
        });