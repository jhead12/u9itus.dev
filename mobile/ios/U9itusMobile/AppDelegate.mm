#import "AppDelegate.h"

#import <FirebaseCore/FirebaseCore.h>
#import <React/RCTBundleURLProvider.h>

@implementation AppDelegate

- (BOOL)application:(UIApplication *)application didFinishLaunchingWithOptions:(NSDictionary *)launchOptions
{
  if ([FIRApp defaultApp] == nil) {
    NSString *firebaseConfigPath = [[NSBundle mainBundle] pathForResource:@"GoogleService-Info" ofType:@"plist"];
    if (firebaseConfigPath != nil) {
      [FIRApp configure];
    } else {
      NSLog(@"[Firebase] Skipping FIRApp.configure(): GoogleService-Info.plist not found in app bundle.");
    }
  }

  self.moduleName = @"U9itusMobile";
  // You can add your custom initial props in the dictionary below.
  // They will be passed down to the ViewController used by React Native.
  self.initialProps = @{};

  return [super application:application didFinishLaunchingWithOptions:launchOptions];
}

- (NSURL *)sourceURLForBridge:(RCTBridge *)bridge
{
  return [self getBundleURL];
}

- (NSURL *)getBundleURL
{
#if DEBUG
  return [[RCTBundleURLProvider sharedSettings] jsBundleURLForBundleRoot:@"index"];
#else
  return [[NSBundle mainBundle] URLForResource:@"main" withExtension:@"jsbundle"];
#endif
}

@end
