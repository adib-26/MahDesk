import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * One-click light/dark toggle. Tracks the effective theme on <html> so it
 * stays in sync with the three-way control on the appearance settings page.
 */
export default function AppearanceToggle({ className }: { className?: string }) {
    const { updateAppearance } = useAppearance();
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        const root = document.documentElement;
        setIsDark(root.classList.contains('dark'));

        const observer = new MutationObserver(() => setIsDark(root.classList.contains('dark')));
        observer.observe(root, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    return (
        <Button
            variant="ghost"
            size="icon"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            className={cn('size-9', className)}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
        >
            {isDark ? <Sun className="rotate-0 transition-transform" /> : <Moon className="transition-transform" />}
        </Button>
    );
}
