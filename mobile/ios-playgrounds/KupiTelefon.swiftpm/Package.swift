// swift-tools-version: 5.8

import PackageDescription
import AppleProductTypes

let package = Package(
    name: "KupiTelefon",
    platforms: [
        .iOS("16.0")
    ],
    products: [
        .iOSApplication(
            name: "KupiTelefon",
            targets: ["AppModule"],
            bundleIdentifier: "rs.kupitelefon.app",
            teamIdentifier: "XXXXXXXXXX",
            displayVersion: "1.0",
            bundleVersion: "1",
            appIcon: .asset("AppIcon"),
            accentColor: .asset("AccentColor"),
            supportedDeviceFamilies: [
                .pad,
                .phone
            ],
            supportedInterfaceOrientations: [
                .portrait,
                .landscapeRight,
                .landscapeLeft
            ],
            capabilities: [
                .outgoingNetworkConnections()
            ]
        )
    ],
    targets: [
        .executableTarget(
            name: "AppModule",
            path: "."
        )
    ]
)
