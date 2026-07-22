<!DOCTYPE html>
<html>
<head>
    <title>CCAvenue Test Redirect</title>
    <meta name="referrer" content="no-referrer">
</head>
<body>
    <h2>CCAvenue Direct URL Test</h2>

    <input id="url" type="text" value="https://secure.ccavenue.ae//transaction//transaction.do?command=initiateTransaction&encRequest=b9c077784024d3118b1de60d6b68f290c646603d1f47c5458d4e1d1d344fec5e9cbad127680af96ad056de3b91f4856fffa4d03653ecb6c4fd4e6820d5d64d99691859e4a93a8ed5c8ca66b7f903fafd7624a872e1fb07a962bf22a0642482a47644753ca4911364a6f8e63f72a804460d7a8cdc14402ac13de90f888b00072af21baa59ec7f6bff2b25198733ee2ba7029240761c8d1bfa0db5d73b1468de358aac6dc764ecb5b9870f15e7e52bbef476811664057acba4194b6ecb3acdac0aa1bbf485611bfee0704dd8c163f9f46b1fb655eb43d936f316b6e4492157f2b7db830a4c3d4e8ded073ad43c09ecaed8351ed6ed6b3516e1d6fe339a415cac4e2f1dcd269ded6af72f16c03b6feb8fad82b88202bedadd0763ebd61287983f8ad588252e877a9a25b9776e3f0db9e0d19398c9e0770803697cd7ab8c0468fae7&access_code=AVWG04JH37AS18GWSA" placeholder="Paste your full CCAvenue URL here" style="width:400px;">
    <br><br>

    <button onclick="redirect()">Redirect</button>

    <script>
        function redirect() {
            const url = document.getElementById('url').value;
            if (!url) {
                alert("Paste the URL first!");
                return;
            }
            window.location.href = url;
        }
    </script>
</body>
</html>
