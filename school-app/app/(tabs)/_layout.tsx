// @ts-nocheck
// app/(tabs)/_layout.tsx — Student tabs with safe area for Android nav bar

import { Tabs } from 'expo-router';
import { Platform, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

const ACTIVE   = '#378ADD';
const INACTIVE = '#B0B7C3';

function Icon({ emoji, focused }) {
  return (
    <View style={{ alignItems: 'center', paddingTop: 2 }}>
      <Text style={{ fontSize: 22, opacity: focused ? 1 : 0.4 }}>{emoji}</Text>
    </View>
  );
}

export default function TabLayout() {
  const insets = useSafeAreaInsets();

  const bottomPadding = Math.max(insets.bottom, Platform.OS === 'android' ? 12 : 0);
  const tabHeight     = 54 + bottomPadding;

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor:   ACTIVE,
        tabBarInactiveTintColor: INACTIVE,
        tabBarStyle: {
          height:          tabHeight,
          paddingBottom:   bottomPadding,
          paddingTop:      6,
          backgroundColor: '#FFFFFF',
          borderTopWidth:  1,
          borderTopColor:  '#EFEFEF',
          elevation: 12,
          shadowColor:   '#000',
          shadowOpacity: 0.1,
          shadowOffset:  { width: 0, height: -2 },
          shadowRadius:  8,
        },
        tabBarLabelStyle: {
          fontSize:   10,
          fontWeight: '600',
          marginTop:  2,
        },
      }}
    >
      <Tabs.Screen name="today"   options={{ title: 'Today',    tabBarIcon: ({ focused }) => <Icon emoji="🏠" focused={focused} /> }} />
      <Tabs.Screen name="subjects" options={{ title: 'Subjects', tabBarIcon: ({ focused }) => <Icon emoji="📚" focused={focused} /> }} />
      <Tabs.Screen name="grades"  options={{ title: 'Grades',   tabBarIcon: ({ focused }) => <Icon emoji="📊" focused={focused} /> }} />
      <Tabs.Screen name="study"   options={{ title: 'Study AI', tabBarIcon: ({ focused }) => <Icon emoji="🎓" focused={focused} /> }} />
      <Tabs.Screen name="market"  options={{ title: 'Market',   tabBarIcon: ({ focused }) => <Icon emoji="🛍️" focused={focused} /> }} />
      <Tabs.Screen name="chat"    options={{ title: 'Chat',     tabBarIcon: ({ focused }) => <Icon emoji="💬" focused={focused} /> }} />
      <Tabs.Screen name="profile" options={{ title: 'Profile',  tabBarIcon: ({ focused }) => <Icon emoji="👤" focused={focused} /> }} />
    </Tabs>
  );
}
