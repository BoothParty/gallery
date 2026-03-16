export default function Navbar() {
    return (
        <nav className="fixed z-50 w-full backdrop-blur p-6 pb-8">
            <ul className="flex justify-around font-syne text-lg font-bold text-white">
                <li>
                    <button className="hover:text-primary hover:cursor-pointer">Accueil</button>
                </li>
                <li>
                    <button className="hover:text-primary hover:cursor-pointer">Photos</button>
                </li>
            </ul>
        </nav>
    );
}
