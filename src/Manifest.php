<?php

namespace MadeByClowd\Nusantara;

class Manifest
{
    /**
     * Map of dataset filenames to their expected SHA-256 checksums.
     *
     * @var array<string, string>
     */
    public static array $hashes = [
        'districts.csv.gz' => '16d8fef5c840a2345f881c440361e82d5bb6f7e190fef3065f557c2fec409c05',
        'provinces.csv.gz' => '3f8e8d21d55353c4d244729616b603a67f74e82b3f0342f5157b3b036556c022',
        'regencies.csv.gz' => '07fc6f3c129c163e875a185b072e1b701486be6f1912161e3fcfa417882638fe',
        'villages_11.csv.gz' => 'fe889a92826af3f6c6dc8fde71fbbae244fddb311e94cce708311d0ee53ea6c4',
        'villages_12.csv.gz' => '2262a9a88ffa9a2f8f24765fe2115463926331c00abbf20c8690cc88c0b2b638',
        'villages_13.csv.gz' => '2d22914afc1f424175b809d42f50a2e546b467719684b115650bb291a85d5c05',
        'villages_14.csv.gz' => 'b9e71c3713d97f272357dfed8c0ee265cf54c8c0c624c9d6beb2ca25c59c92da',
        'villages_15.csv.gz' => 'bea8bcde50c20951ad56e73448e02f83d9cb35f78d91c8c94739868cac6975ff',
        'villages_16.csv.gz' => '1b00e56f20f5c1313e8494ca394a6c7e1003f3649358baded001548cb0454496',
        'villages_17.csv.gz' => 'f6d6505ad8e13b148d7bdf77c57804caf05632cb8fb1e70ec6ec7409651fc376',
        'villages_18.csv.gz' => 'd4c98f685da126dd0a47c4a420434b434205a7b1f9ad0b7e6fea4612b860338a',
        'villages_19.csv.gz' => '71ef36b7c9287aecddafb10338bbe0fb245ed2ec2b8badc18c833d0d2d030289',
        'villages_21.csv.gz' => 'ba0b2a5ae707913809c1ef02ab650baa34464233c636e4f2fb997b226f8be367',
        'villages_31.csv.gz' => '2001b611fdbf117e424c7d02ae771ae73628938b26e2bb21c74a4a62f6dde29c',
        'villages_32.csv.gz' => '703915642a22ccb4e1ecb777cda45dfd565fff847b74ec0b6935fb8e0395e862',
        'villages_33.csv.gz' => 'ed9a2aa3a41fac7c0285543bd30e0781e8a1d68e6745af98f1cc327f8bbe3a2b',
        'villages_34.csv.gz' => '262419643f75e608830c2df8d89974d043b1958bd78c9f1cad8b49073154b755',
        'villages_35.csv.gz' => '3a3a3ae28dafce03125887f10faca1c388a59e5474b60d433d42210f1d5816a8',
        'villages_36.csv.gz' => 'f8cc5709b11c067cc14e7000a37d679aa683d6f96a6b437b6c893c5371d26505',
        'villages_51.csv.gz' => '055f4b075da71950addacb917c81cd2fa3f58681a3c328a0034903b97e1312c8',
        'villages_52.csv.gz' => '5b0632a6609e2ab22b7fed05d6866a8900b00e78ab202b1e918eff39c17927f8',
        'villages_53.csv.gz' => 'e87c3bd15f4b2185b1b9ad7d0c7bf6b2c2f4f56656995cdafa6773efff5d9b5f',
        'villages_61.csv.gz' => '2f14a2d7d46b0ea6371bbcebcdc85926c3513c3535e7091bd1d4b04f6e8e5787',
        'villages_62.csv.gz' => '01c09e3e601866d9cb3acc00718eae2e88090af3a199626f9b85e406304b7cfd',
        'villages_63.csv.gz' => '568b835e6555c654a1c9036e88650bc6075c40d8d67b201ca716f68b857492f1',
        'villages_64.csv.gz' => '6ad124d90bcdf54547eaad3cd8ef3d4b0b414110de74ca2156f2d2561a4d7ba2',
        'villages_65.csv.gz' => 'da0efb18c76c0cc9223284966ee727fad647f76ab3f4097dc637f8ff5685b7b3',
        'villages_71.csv.gz' => '936a169aff3369465328cfc288ec4d74d4143071e758bbd71a39675d8b61d80d',
        'villages_72.csv.gz' => '9a4c9c6310621a0ae3633e46515067b4e72f8918c3e1b1c184ed9d5e099fc5ee',
        'villages_73.csv.gz' => '0e3a1dcffb359a4a1a09076008d2b958a0f7af26a2f8add3d371254822fe6f49',
        'villages_74.csv.gz' => '874337fa3308c2dd92ffec9c86abaf958f48ee36f4a7c2e4d1585ae4e00555da',
        'villages_75.csv.gz' => 'cc0861ef2ec0af3ce59ebfe2e44fb507831c50ee2c538c2180e4a945fd6d68ed',
        'villages_76.csv.gz' => 'f9a79cd0335842f00a171e83cd6bbec9ab7cedcfc9d284b93404bba71e1776a9',
        'villages_81.csv.gz' => '6904f311f4c11b0ff6a0f86eae123110dd307cbe653148cb5a46cf2a63d3375a',
        'villages_82.csv.gz' => '42f1b44064eda596025b5707341ed55558730b826c75520460826690996e9511',
        'villages_91.csv.gz' => '5cb6a39b87936ccd8b8279888fb377a0a11d0d07209a66641a7930424afa9710',
        'villages_92.csv.gz' => '79975807fce1e22b6cbfc3d0bac87304053017d9628726b2c4ab7d3f7799bfe1',
        'villages_93.csv.gz' => '9c2c6ca517e4ef8f7bb461258d18b340238dd5a54f94eae37e65efc8861fd37e',
        'villages_94.csv.gz' => '43d7951712dcc291056cfa45e0ec67f13839fcdb88168e3c42a03fdce3ab9ba6',
        'villages_95.csv.gz' => '650f3d935098a758304b01202ed759f07f77531ae37c3024fbf60fb98b762003',
        'villages_96.csv.gz' => 'cbd5c4169436a097e251044f8ba16e3d1f5f036fa84b18ac877a7dc20e1ff315',
    ];

    /**
     * Get the expected SHA-256 hash for a filename.
     */
    public static function get(string $filename): ?string
    {
        return static::$hashes[$filename] ?? null;
    }
}
