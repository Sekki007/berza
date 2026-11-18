// swift-tools-version: 5.9

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
            teamIdentifier: "",
            displayVersion: "1.0",
            bundleVersion: "1",
            appIcon: .placeholder(icon: .phone),
            accentColor: .presetColor(.green),
            supportedDeviceFamilies: [
                .pad,
                .phone
            ],
            supportedInterfaceOrientations: [
                .portrait,
                .landscapeRight,
                .landscapeLeft,
                .portraitUpsideDown(.when(deviceFamilies: [.pad]))
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
